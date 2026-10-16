<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\FollowUp;
use App\Models\TerminRevision;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Department;
use App\Models\Workflow;
use App\Models\ReportWidget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportQueryEngineService
{
    /**
     * Power BI stili veri semantik sözlüğü (Data Sources Metadata)
     * Çekirdek BPM modellerini ve sistemdeki TÜM dinamik form şablonlarını (Preform, Kapak, Levha vb.) otomatik keşfeder.
     */
    public function getAvailableDataSources(): array
    {
        $sources = [
            'processes' => [
                'id' => 'processes',
                'label' => '🚀 Süreçler & İş Akışı Performansı',
                'description' => 'Tüm süreçlerin hacim, tamamlanma süreleri, departman ve durum analitiği',
                'dimensions' => [
                    ['field' => 'workflow_name', 'label' => 'İş Akışı Adı', 'type' => 'string'],
                    ['field' => 'status', 'label' => 'Süreç Durumu', 'type' => 'status'],
                    ['field' => 'starter_department', 'label' => 'Başlatan Departman', 'type' => 'string'],
                    ['field' => 'starter_name', 'label' => 'Talep Sahibi', 'type' => 'string'],
                    ['field' => 'created_month', 'label' => 'Başlatılma Ayı (Trend)', 'type' => 'date_month'],
                    ['field' => 'created_date', 'label' => 'Başlatılma Günü', 'type' => 'date_day'],
                ],
                'metrics' => [
                    ['field' => 'id', 'label' => 'Süreç Adedi', 'supported_aggs' => ['COUNT']],
                    ['field' => 'duration_hours', 'label' => 'Ortalama Çevrim Süresi (Saat)', 'supported_aggs' => ['AVG', 'MAX']],
                    ['field' => 'duration_days', 'label' => 'Ortalama Tamamlanma Süresi (Gün)', 'supported_aggs' => ['AVG', 'MAX']],
                ],
            ],
            'tasks' => [
                'id' => 'tasks',
                'label' => '⏱️ Görev, Onay & Karar Dağılımı',
                'description' => 'Onay adımları, ret/revizyon sayıları, yetkili ve departman iş yükü',
                'dimensions' => [
                    ['field' => 'department_name', 'label' => 'Sorumlu Departman', 'type' => 'string'],
                    ['field' => 'assigned_user_name', 'label' => 'Atanan Yetkili', 'type' => 'string'],
                    ['field' => 'status', 'label' => 'Görev Durumu', 'type' => 'status'],
                    ['field' => 'decision', 'label' => 'Karar Türü (Onay / Ret / Revize)', 'type' => 'string'],
                    ['field' => 'created_month', 'label' => 'Oluşturulma Ayı', 'type' => 'date_month'],
                ],
                'metrics' => [
                    ['field' => 'id', 'label' => 'Görev Adedi', 'supported_aggs' => ['COUNT']],
                    ['field' => 'completion_hours', 'label' => 'Ort. Karar Verme Süresi (Saat)', 'supported_aggs' => ['AVG', 'MAX']],
                ],
            ],
            'termin_sla' => [
                'id' => 'termin_sla',
                'label' => '🎯 Termin, SLA & Kök Neden Analitiği',
                'description' => 'Üretim termin sapmaları, arıza/kalıp/hammadde gecikme nedenleri',
                'dimensions' => [
                    ['field' => 'reason_category', 'label' => 'Gecikme Kök Nedeni (Arıza/Hammadde/vb.)', 'type' => 'string'],
                    ['field' => 'status', 'label' => 'Termin Revizyon Durumu', 'type' => 'status'],
                    ['field' => 'requested_by_name', 'label' => 'Revizyon İsteyen Yetkili', 'type' => 'string'],
                    ['field' => 'created_month', 'label' => 'Talep Ayı', 'type' => 'date_month'],
                ],
                'metrics' => [
                    ['field' => 'id', 'label' => 'Revizyon / Erteleme Adedi', 'supported_aggs' => ['COUNT']],
                ],
            ],
            'follow_ups' => [
                'id' => 'follow_ups',
                'label' => '💼 Numune Yaşam Döngüsü & Satış Dönüşümü',
                'description' => 'Numunelerin siparişe dönüşme oranları (% Conversion) ve SAP sipariş takibi',
                'dimensions' => [
                    ['field' => 'status', 'label' => 'Dönüşüm Durumu (Converted / Lost)', 'type' => 'status'],
                    ['field' => 'lost_reason', 'label' => 'Kayıp Nedeni (Fiyat/Teknik/vb.)', 'type' => 'string'],
                    ['field' => 'created_month', 'label' => 'Takip Ayı', 'type' => 'date_month'],
                ],
                'metrics' => [
                    ['field' => 'id', 'label' => 'Numune Adedi', 'supported_aggs' => ['COUNT']],
                    ['field' => 'annual_potential_tonnage', 'label' => 'Yıllık Potansiyel Tonaj', 'supported_aggs' => ['SUM', 'AVG']],
                ],
            ],
        ];

        // DİNAMİK FORM ŞABLONLARINI VERİTABANINDAN OTOMATİK KEŞFET (Auto-Discovery Engine)
        // İleride Kapak, Preform, Kalıp Bakım veya yeni bir form eklendiğinde BURADA KOD YAZMADAN otomatik listelenir!
        try {
            $templates = FormTemplate::where('is_active', true)->get();
            foreach ($templates as $t) {
                // Geriye dönük uyumluluk için template 4 hem form_fr_lvh_042 hem form_4 olarak tanınabilir
                $sourceKey = ($t->id === 4) ? 'form_fr_lvh_042' : ('form_' . $t->id);
                $docBadge = $t->document_no ? " [{$t->document_no}]" : "";

                $dimensions = [];
                $metrics = [
                    ['field' => 'id', 'label' => 'Form Başvuru Adedi', 'supported_aggs' => ['COUNT']],
                ];

                $schema = is_array($t->schema) ? $t->schema : json_decode($t->schema, true);
                if (is_array($schema)) {
                    $fields = $schema['fields'] ?? (is_numeric(array_key_first($schema)) ? $schema : []);
                    foreach ($fields as $f) {
                        $type = $f['type'] ?? 'text';
                        if (in_array($type, ['header', 'paragraph', 'divider', 'alert', 'signature'])) {
                            continue;
                        }

                        $fieldKey = $f['name'] ?? ($f['model'] ?? ($f['id'] ?? null));
                        if (!$fieldKey) continue;

                        $fieldLabel = $f['label'] ?? ($f['title'] ?? $fieldKey);

                        $dimensions[] = [
                            'field' => 'data.' . $fieldKey,
                            'label' => $fieldLabel,
                            'type' => $type,
                        ];

                        if (in_array($type, ['number', 'currency', 'decimal'])) {
                            $metrics[] = [
                                'field' => 'data.' . $fieldKey,
                                'label' => 'Ortalama / Toplam ' . $fieldLabel,
                                'supported_aggs' => ['AVG', 'SUM', 'MIN', 'MAX'],
                            ];
                        }
                    }
                }

                $sources[$sourceKey] = [
                    'id' => $sourceKey,
                    'label' => '📋 ' . $t->name . $docBadge,
                    'description' => $t->description ?: ($t->name . ' dinamik form alanları ve analitiği'),
                    'dimensions' => $dimensions,
                    'metrics' => $metrics,
                ];
            }
        } catch (\Throwable $e) {
            // Veritabanı henüz hazır değilse sessizce geç
        }

        return $sources;
    }

    /**
     * Tek bir widget konfigürasyonunu sorgular ve ApexCharts / Tablo formatına çevirir
     */
    public function executeWidgetQuery(array|ReportWidget $widget, array $runtimeFilters = []): array
    {
        $config = is_array($widget) ? $widget : $widget->toArray();
        $dataSource = $config['data_source'] ?? 'processes';
        $chartType = $config['chart_type'] ?? 'bar';
        $dimension = $config['dimension_field'] ?? 'status';
        $metric = $config['metric_field'] ?? 'id';
        $aggregation = strtoupper($config['aggregation_type'] ?? 'COUNT');
        $colorPalette = $config['color_palette'] ?? 'indigo';

        // Filtreleri birleştir (Kayıtlı filtreler + Çalışma zamanı filtreleri)
        $filters = array_merge(
            is_array($config['filters_json'] ?? null) ? $config['filters_json'] : [],
            $runtimeFilters
        );

        if ($dataSource === 'processes') {
            $rawResult = $this->queryProcesses($dimension, $metric, $aggregation, $filters);
        } elseif ($dataSource === 'tasks') {
            $rawResult = $this->queryTasks($dimension, $metric, $aggregation, $filters);
        } elseif ($dataSource === 'termin_sla') {
            $rawResult = $this->queryTerminSla($dimension, $metric, $aggregation, $filters);
        } elseif ($dataSource === 'follow_ups') {
            $rawResult = $this->queryFollowUps($dimension, $metric, $aggregation, $filters);
        } elseif ($dataSource === 'form_fr_lvh_042') {
            $rawResult = $this->queryDynamicForms(4, $dimension, $metric, $aggregation, $filters);
        } elseif (str_starts_with($dataSource, 'form_')) {
            $templateId = (int) substr($dataSource, 5);
            $rawResult = $this->queryDynamicForms($templateId, $dimension, $metric, $aggregation, $filters);
        } else {
            $rawResult = $this->queryProcesses($dimension, $metric, $aggregation, $filters);
        }

        return $this->formatForVisualization($rawResult, $chartType, $dimension, $metric, $aggregation, $colorPalette);
    }

    /**
     * Süreçler Veri Kaynağı Sorgusu
     */
    protected function queryProcesses(string $dimension, string $metric, string $agg, array $filters): array
    {
        $query = DB::table('process_instances as pi')
            ->leftJoin('workflows as w', 'pi.workflow_id', '=', 'w.id')
            ->leftJoin('users as u', 'pi.started_by', '=', 'u.id')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id');

        $this->applyDateFilter($query, 'pi.created_at', $filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        if (!empty($filters['workflow_id'])) {
            $query->where('pi.workflow_id', $filters['workflow_id']);
        }
        if (!empty($filters['department_id'])) {
            $query->where('u.department_id', $filters['department_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('pi.status', $filters['status']);
        }

        // Boyut ifadesi
        $dimSql = match ($dimension) {
            'workflow_name' => 'COALESCE(w.name, "Bilinmeyen")',
            'status' => 'pi.status',
            'starter_department' => 'COALESCE(d.name, "Departmansız")',
            'starter_name' => 'COALESCE(u.name, "Sistem")',
            'created_month' => "DATE_FORMAT(pi.created_at, '%Y-%m')",
            'created_date' => "DATE(pi.created_at)",
            default => 'pi.status',
        };

        // Metrik ifadesi
        $metricSql = match ($metric) {
            'duration_hours' => "TIMESTAMPDIFF(HOUR, pi.created_at, COALESCE(pi.updated_at, NOW()))",
            'duration_days' => "TIMESTAMPDIFF(DAY, pi.created_at, COALESCE(pi.updated_at, NOW()))",
            default => 'pi.id',
        };

        $aggSql = match ($agg) {
            'AVG' => "ROUND(AVG({$metricSql}), 1)",
            'SUM' => "SUM({$metricSql})",
            'MAX' => "MAX({$metricSql})",
            'MIN' => "MIN({$metricSql})",
            default => "COUNT(pi.id)",
        };

        return $query->selectRaw("{$dimSql} as label, {$aggSql} as value")
            ->groupBy(DB::raw('label'))
            ->orderByRaw("value DESC")
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Görevler Veri Kaynağı Sorgusu
     */
    protected function queryTasks(string $dimension, string $metric, string $agg, array $filters): array
    {
        $query = DB::table('tasks as t')
            ->leftJoin('users as u', 't.assigned_to', '=', 'u.id')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->leftJoin('process_instances as pi', 't.process_instance_id', '=', 'pi.id');

        $this->applyDateFilter($query, 't.created_at', $filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        if (!empty($filters['status'])) {
            $query->where('t.status', $filters['status']);
        }
        if (!empty($filters['department_id'])) {
            $query->where('u.department_id', $filters['department_id']);
        }
        if (!empty($filters['workflow_id'])) {
            $query->where('pi.workflow_id', $filters['workflow_id']);
        }

        $dimSql = match ($dimension) {
            'department_name' => 'COALESCE(d.name, "Departmansız")',
            'assigned_user_name' => 'COALESCE(u.name, "Atanmamış")',
            'status' => 't.status',
            'decision' => "CASE 
                WHEN t.status = 'completed' AND t.comment LIKE '%reviz%' THEN 'Revize Edildi'
                WHEN t.status = 'completed' THEN 'Onaylandı / Tamamlandı'
                WHEN t.status = 'cancelled' THEN 'İptal / Reddedildi'
                ELSE 'Bekliyor' END",
            'created_month' => "DATE_FORMAT(t.created_at, '%Y-%m')",
            default => 't.status',
        };

        $metricSql = match ($metric) {
            'completion_hours' => "TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.updated_at, NOW()))",
            default => 't.id',
        };

        $aggSql = match ($agg) {
            'AVG' => "ROUND(AVG({$metricSql}), 1)",
            'MAX' => "MAX({$metricSql})",
            default => "COUNT(t.id)",
        };

        return $query->selectRaw("{$dimSql} as label, {$aggSql} as value")
            ->groupBy(DB::raw('label'))
            ->orderByRaw("value DESC")
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Termin ve SLA Revizyon Analitiği
     */
    protected function queryTerminSla(string $dimension, string $metric, string $agg, array $filters): array
    {
        $query = DB::table('termin_revisions as tr')
            ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id')
            ->leftJoin('process_instances as pi', 'tr.process_instance_id', '=', 'pi.id');

        $this->applyDateFilter($query, 'tr.created_at', $filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        if (!empty($filters['department_id'])) {
            $query->where('u.department_id', $filters['department_id']);
        }
        if (!empty($filters['workflow_id'])) {
            $query->where('pi.workflow_id', $filters['workflow_id']);
        }

        $dimSql = match ($dimension) {
            'reason_category' => "CASE tr.reason_category
                WHEN 'raw_material' THEN 'Hammadde / Tedarik Gecikmesi'
                WHEN 'machine_breakdown' THEN 'Hattı / Makine Arızası'
                WHEN 'mold_change' THEN 'Kalıp Değişimi & Ayar'
                WHEN 'quality_retest' THEN 'Kalite Tekrar Testi'
                WHEN 'customer_change' THEN 'Müşteri Spesifikasyon Değişimi'
                ELSE 'Diğer Operasyonel Gecikme' END",
            'status' => 'tr.status',
            'requested_by_name' => 'COALESCE(u.name, "Sistem")',
            'created_month' => "DATE_FORMAT(tr.created_at, '%Y-%m')",
            default => 'tr.reason_category',
        };

        return $query->selectRaw("{$dimSql} as label, COUNT(tr.id) as value")
            ->groupBy(DB::raw('label'))
            ->orderByRaw("value DESC")
            ->get()
            ->toArray();
    }

    /**
     * Follow-Up (Numune Satış Dönüşümü) Sorgusu
     */
    protected function queryFollowUps(string $dimension, string $metric, string $agg, array $filters): array
    {
        $query = DB::table('follow_ups as fu')
            ->leftJoin('process_instances as pi', 'fu.process_instance_id', '=', 'pi.id')
            ->leftJoin('users as u', 'fu.assigned_to', '=', 'u.id');

        $this->applyDateFilter($query, 'fu.created_at', $filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        if (!empty($filters['department_id'])) {
            $query->where('u.department_id', $filters['department_id']);
        }
        if (!empty($filters['workflow_id'])) {
            $query->where('pi.workflow_id', $filters['workflow_id']);
        }

        $dimSql = match ($dimension) {
            'status' => "CASE COALESCE(fu.response_status, fu.status)
                WHEN 'converted' THEN 'Siparişe Döndü (Converted)'
                WHEN 'not_converted' THEN 'Siparişe Dönmedi (Lost)'
                ELSE 'Bekliyor / Takipte' END",
            'lost_reason' => 'COALESCE(fu.non_conversion_reason, "Belirtilmemiş")',
            'created_month' => "DATE_FORMAT(fu.created_at, '%Y-%m')",
            default => 'fu.status',
        };

        $aggSql = match ($metric) {
            default => "COUNT(fu.id)",
        };

        return $query->selectRaw("{$dimSql} as label, {$aggSql} as value")
            ->groupBy(DB::raw('label'))
            ->orderByRaw("value DESC")
            ->get()
            ->toArray();
    }

    /**
     * Dinamik JSON Form Alanları Sorgusu (EAV Query Engine)
     */
    protected function queryDynamicForms(int $formTemplateId, string $dimension, string $metric, string $agg, array $filters): array
    {
        $query = DB::table('form_submissions as fs')
            ->join('process_instances as pi', 'fs.process_instance_id', '=', 'pi.id')
            ->leftJoin('workflows as w', 'pi.workflow_id', '=', 'w.id')
            ->leftJoin('users as u', 'pi.started_by', '=', 'u.id')
            ->where(function($q) use ($formTemplateId) {
                $q->where('w.form_template_id', $formTemplateId)
                  ->orWhere('w.nodes', 'like', '%"form_template_id":' . $formTemplateId . '%')
                  ->orWhere('w.nodes', 'like', '%"sub_form_id":' . $formTemplateId . '%');
                if ($formTemplateId === 4) {
                    $q->orWhere('pi.workflow_id', 7);
                }
            });

        $this->applyDateFilter($query, 'fs.created_at', $filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        if (!empty($filters['department_id'])) {
            $query->where('u.department_id', $filters['department_id']);
        }
        if (!empty($filters['workflow_id'])) {
            $query->where('pi.workflow_id', $filters['workflow_id']);
        }


        // 'data.urun_grubu' formatındaki boyutu JSON_UNQUOTE(JSON_EXTRACT(data, '$.urun_grubu'))'ya çevir
        if (str_starts_with($dimension, 'data.')) {
            $jsonKey = substr($dimension, 5);
            $dimSql = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fs.data, '$.\"{$jsonKey}\"')), 'null'), 'Tanımsız')";
        } else {
            $dimSql = "fs.id";
        }

        // Metrik
        if (str_starts_with($metric, 'data.')) {
            $jsonKey = substr($metric, 5);
            $valSql = "CAST(JSON_UNQUOTE(JSON_EXTRACT(fs.data, '$.\"{$jsonKey}\"')) AS DECIMAL(10,2))";
            $aggSql = match ($agg) {
                'AVG' => "ROUND(AVG({$valSql}), 1)",
                'SUM' => "SUM({$valSql})",
                default => "COUNT(fs.id)",
            };
        } else {
            $aggSql = "COUNT(fs.id)";
        }

        return $query->selectRaw("{$dimSql} as label, {$aggSql} as value")
            ->groupBy(DB::raw('label'))
            ->orderByRaw("value DESC")
            ->limit(15)
            ->get()
            ->toArray();
    }

    /**
     * Tarih aralığı filtresini Query Builder'a uygular (Preset veya Takvimden Özel Tarih Aralığı)
     */
    protected function applyDateFilter($query, string $dateColumn, ?string $range = null, ?string $startDate = null, ?string $endDate = null): void
    {
        // 1. Doğrudan Takvim Seçimi (Custom Calendar Range)
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
            return;
        } elseif (!empty($startDate)) {
            $query->whereDate($dateColumn, '>=', Carbon::parse($startDate));
            return;
        } elseif (!empty($endDate)) {
            $query->whereDate($dateColumn, '<=', Carbon::parse($endDate));
            return;
        }

        // 2. Preset Filtreler
        if (!$range || $range === 'all_time') {
            return;
        }

        match ($range) {
            'today' => $query->whereDate($dateColumn, Carbon::today()),
            'this_week' => $query->whereBetween($dateColumn, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'this_month' => $query->whereMonth($dateColumn, Carbon::now()->month)->whereYear($dateColumn, Carbon::now()->year),
            'this_year' => $query->whereYear($dateColumn, Carbon::now()->year),
            'last_30_days' => $query->where($dateColumn, '>=', Carbon::now()->subDays(30)),
            'last_90_days' => $query->where($dateColumn, '>=', Carbon::now()->subDays(90)),
            default => null,
        };
    }

    /**
     * Ham SQL sonuçlarını ApexCharts ve Vue formatına biçimlendirir
     */
    protected function formatForVisualization(array $data, string $chartType, string $dimension, string $metric, string $agg, string $palette): array
    {
        $categories = [];
        $values = [];
        $totalSum = 0;

        foreach ($data as $row) {
            $categories[] = (string)($row->label ?? 'Diğer');
            $numVal = is_numeric($row->value) ? (float)$row->value : 0;
            $values[] = $numVal;
            $totalSum += $numVal;
        }

        // Renk paleti haritası
        $colorMaps = [
            'indigo' => ['#6366F1', '#818CF8', '#A5B4FC', '#C7D2FE', '#4F46E5', '#4338CA'],
            'emerald' => ['#10B981', '#34D399', '#6EE7B7', '#A7F3D0', '#059669', '#047857'],
            'blue' => ['#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE', '#2563EB', '#1D4ED8'],
            'amber' => ['#F59E0B', '#FBBF24', '#FCD34D', '#FDE68A', '#D97706', '#B45309'],
            'rose' => ['#F43F5E', '#FB7185', '#FDA4AF', '#FECDD3', '#E11D48', '#BE123C'],
        ];
        $selectedColors = $colorMaps[$palette] ?? $colorMaps['indigo'];

        // KPI Kartı için tekil gösterim
        if ($chartType === 'kpi_card') {
            $mainValue = count($values) > 0 ? ($agg === 'AVG' ? round($totalSum / count($values), 1) : $totalSum) : 0;
            return [
                'type' => 'kpi_card',
                'value' => $mainValue,
                'categories' => $categories,
                'series' => $values,
                'colors' => $selectedColors,
            ];
        }

        // Donut / Pie grafikler için series sadece [10, 20, 30] dizisidir
        if ($chartType === 'donut' || $chartType === 'pie') {
            return [
                'type' => $chartType,
                'categories' => $categories,
                'series' => $values,
                'colors' => $selectedColors,
                'total' => $totalSum,
            ];
        }

        // Bar / Line / Area için ApexCharts formatı: [{ name: 'Değer', data: [10, 20, 30] }]
        return [
            'type' => $chartType,
            'categories' => $categories,
            'series' => [
                [
                    'name' => match ($agg) {
                        'AVG' => 'Ortalama',
                        'SUM' => 'Toplam',
                        default => 'Adet',
                    },
                    'data' => $values,
                ]
            ],
            'colors' => $selectedColors,
            'total' => $totalSum,
            'table_rows' => array_map(function ($cat, $val) {
                return ['label' => $cat, 'value' => $val];
            }, $categories, $values),
        ];
    }
}
