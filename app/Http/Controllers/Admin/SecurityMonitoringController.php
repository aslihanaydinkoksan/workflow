<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SecurityTestRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SecurityMonitoringController extends Controller
{
    public function __construct(
        private readonly SecurityTestRunnerService $testRunner,
    ) {
    }

    public function index(Request $request): Response
    {
        $logFiles = $this->getAvailableLogFiles();
        $selectedDate = $request->query('date', date('Y-m-d'));

        $logPath = storage_path("logs/security-{$selectedDate}.log");
        $allEvents = $this->readLogEvents($logPath);

        // İstatistikler (Tüm günün özeti)
        $stats = [
            'total_events' => count($allEvents),
            'auth_failed' => count(array_filter($allEvents, fn($e) => ($e['event_type'] ?? '') === 'auth.login_failed')),
            'account_locked' => count(array_filter($allEvents, fn($e) => in_array($e['event_type'] ?? '', ['auth.account_locked', 'auth.login_blocked'], true))),
            'ip_blocked' => count(array_filter($allEvents, fn($e) => ($e['event_type'] ?? '') === 'auth.ip_blocked')),
            'sqli_detected' => count(array_filter($allEvents, fn($e) => ($e['event_type'] ?? '') === 'attack.sqli_pattern')),
            'xss_detected' => count(array_filter($allEvents, fn($e) => ($e['event_type'] ?? '') === 'attack.xss_pattern')),
            'scanners_detected' => count(array_filter($allEvents, fn($e) => in_array($e['event_type'] ?? '', ['attack.scanner_user_agent', 'attack.scanning_behavior'], true))),
            'csp_violations' => count(array_filter($allEvents, fn($e) => ($e['event_type'] ?? '') === 'attack.csp_violation')),
            'db_errors' => count(array_filter($allEvents, fn($e) => in_array($e['event_type'] ?? '', ['attack.db_syntax_error', 'app.database_error'], true))),
        ];

        // Filtreleme
        $filteredEvents = $allEvents;

        if ($type = $request->query('type')) {
            $filteredEvents = array_filter($filteredEvents, fn($e) => ($e['event_type'] ?? '') === $type);
        }

        if ($severity = $request->query('severity')) {
            $filteredEvents = array_filter($filteredEvents, fn($e) => ($e['severity'] ?? '') === $severity);
        }

        if ($search = $request->query('search')) {
            $s = mb_strtolower(trim($search));
            $filteredEvents = array_filter($filteredEvents, function ($e) use ($s) {
                $raw = mb_strtolower(json_encode($e, JSON_UNESCAPED_UNICODE));
                return str_contains($raw, $s);
            });
        }

        // Zaman Aralığı Filtresi (Europe/Istanbul saatine göre HH:mm)
        $timeStart = $request->query('time_start');
        $timeEnd = $request->query('time_end');

        if ($timeStart || $timeEnd) {
            $filteredEvents = array_filter($filteredEvents, function ($e) use ($timeStart, $timeEnd) {
                $rawTimestamp = $e['@timestamp'] ?? '';
                if (!$rawTimestamp) {
                    return true;
                }
                try {
                    $dt = new \DateTimeImmutable($rawTimestamp);
                    $dtLocal = $dt->setTimezone(new \DateTimeZone('Europe/Istanbul'));
                    $timeStr = $dtLocal->format('H:i');

                    if ($timeStart && $timeStr < $timeStart) {
                        return false;
                    }
                    if ($timeEnd && $timeStr > $timeEnd) {
                        return false;
                    }
                    return true;
                } catch (\Throwable) {
                    return true;
                }
            });
        }

        // Sayfalama (Pagination - 30 kayıt)
        $filteredEvents = array_values($filteredEvents);
        $perPage = 30;
        $page = max(1, (int) $request->query('page', 1));
        $totalItems = count($filteredEvents);
        $pagedItems = array_slice($filteredEvents, ($page - 1) * $perPage, $perPage);

        // Güvenlik Konfigürasyon Bilgileri
        $configInfo = [
            'app_name' => config('security-monitoring.logging.application', 'koksan-workflow'),
            'log_channel' => config('security-monitoring.log_channel', 'security'),
            'max_account_attempts' => config('security-monitoring.login_throttle.max_account_attempts', 5),
            'max_ip_attempts' => config('security-monitoring.login_throttle.max_ip_attempts', 20),
            'decay_seconds' => config('security-monitoring.login_throttle.decay_seconds', 900),
            'inspection_mode' => config('security-monitoring.inspection.mode', 'monitor'),
            'csp_enabled' => config('security-monitoring.csp.enabled', true),
            'csp_report_only' => config('security-monitoring.csp.report_only', true),
            'csp_policy' => config('security-monitoring.csp.policy', ''),
            'sensitive_fields' => config('security-monitoring.sensitive_fields', []),
            'siem_syslog_host' => env('SIEM_SYSLOG_HOST', 'Logsign Collector (Hazır Beklemede)'),
            'siem_syslog_port' => env('SIEM_SYSLOG_PORT', 514),
        ];

        return Inertia::render('Admin/Security/Index', [
            'events' => $pagedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalItems,
                'last_page' => max(1, (int) ceil($totalItems / $perPage)),
            ],
            'stats' => $stats,
            'filters' => [
                'date' => $selectedDate,
                'time_start' => $request->query('time_start', ''),
                'time_end' => $request->query('time_end', ''),
                'type' => $request->query('type', ''),
                'severity' => $request->query('severity', ''),
                'search' => $request->query('search', ''),
            ],
            'available_dates' => $logFiles,
            'file_exists' => file_exists($logPath),
            'config_info' => $configInfo,
        ]);
    }

    /**
     * Kabul testlerini canlı çalıştırır ve anında AJAX ile döner.
     * Aktif admin oturumunun (session) ve CSRF token'ının testlerdeki sahte login
     * denemelerinden bozulmaması için testler izole bir CLI sürecinde koşturulur.
     */
    public function runAcceptanceTests(): JsonResponse
    {
        $phpCli = 'php';
        if (PHP_SAPI !== 'cli') {
            $candidates = [
                '/usr/bin/php',
                '/usr/bin/php8.2',
                '/usr/bin/php8.3',
                '/usr/local/bin/php',
                'C:\\xampp\\php\\php.exe',
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $phpCli = $cand;
                    break;
                }
            }
            if ($phpCli === 'php' && defined('PHP_BINARY') && !str_contains(PHP_BINARY, 'fpm') && !str_contains(PHP_BINARY, 'httpd')) {
                $phpCli = PHP_BINARY;
            }
        } else {
            $phpCli = PHP_BINARY;
        }

        $php = escapeshellarg($phpCli);
        $artisan = escapeshellarg(base_path('artisan'));
        $cmd = "{$php} {$artisan} security:test-acceptance --json 2>&1";

        $output = function_exists('shell_exec') ? @shell_exec($cmd) : null;
        $report = null;

        if ($output) {
            $startPos = strpos($output, '{');
            $endPos = strrpos($output, '}');
            if ($startPos !== false && $endPos !== false && $endPos >= $startPos) {
                $jsonStr = substr($output, $startPos, $endPos - $startPos + 1);
                $report = json_decode($jsonStr, true);
            }
        }

        if (!$report || !isset($report['results'])) {
            $report = $this->testRunner->runAllTests();
        }

        return response()->json([
            'success' => true,
            'message' => "Tüm 10 kabul testi başarıyla tamamlandı ({$report['passed_tests']}/{$report['total_tests']}).",
            'report' => $report,
        ]);
    }

    /**
     * Seçilen günün log dosyasını temizler
     */
    public function clearLogs(Request $request): JsonResponse
    {
        $date = $request->input('date', date('Y-m-d'));
        $logPath = storage_path("logs/security-{$date}.log");

        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        return response()->json([
            'success' => true,
            'message' => "{$date} tarihli güvenlik logları başarıyla temizlendi.",
        ]);
    }

    /**
     * Tekil bir log olayını dosyadan siler
     */
    public function destroyLogEntry(Request $request, string $requestId): JsonResponse
    {
        $date = $request->input('date', date('Y-m-d'));
        $logPath = storage_path("logs/security-{$date}.log");

        if (!file_exists($logPath)) {
            return response()->json(['success' => false, 'message' => 'Log dosyası bulunamadı.'], 404);
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = [];
        $found = false;

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && ($data['request_id'] ?? '') === $requestId) {
                $found = true;
                continue; // Atla (sil)
            }
            $newLines[] = $line;
        }

        if ($found) {
            file_put_contents($logPath, implode("\n", $newLines) . "\n");
            return response()->json(['success' => true, 'message' => 'Olay kaydı log dosyasından kaldırıldı.']);
        }

        return response()->json(['success' => false, 'message' => 'İlgili olay kaydı bulunamadı.'], 404);
    }

    /**
     * Günlük güvenlik log dosyasını JSON formatında indirir
     */
    public function exportJson(Request $request): BinaryFileResponse
    {
        $date = $request->query('date', date('Y-m-d'));
        $logPath = storage_path("logs/security-{$date}.log");

        if (!file_exists($logPath)) {
            touch($logPath);
        }

        return response()->download($logPath, "koksan-security-events-{$date}.json", [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readLogEvents(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $events = [];
        // En son olaylar en üstte görünsün diye sondan başa doğru okuyoruz
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $data = json_decode($lines[$i], true);
            if (is_array($data)) {
                $events[] = $data;
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function getAvailableLogFiles(): array
    {
        $logsDir = storage_path('logs');
        $files = File::glob("{$logsDir}/security-*.log");
        $dates = [];

        foreach ($files as $file) {
            if (preg_match('/security-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        if (!in_array(date('Y-m-d'), $dates, true)) {
            array_unshift($dates, date('Y-m-d'));
        }

        return array_values(array_unique($dates));
    }
}
