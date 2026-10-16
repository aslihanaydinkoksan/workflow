<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenanceOrderGenerator
{
    /**
     * TPM & Makine Arıza / Bakım Onarım Süreci verilerinden resmi İş Emri PDF belgesi oluşturur.
     */
    public function generate(ProcessInstance $instance)
    {
        $instance->loadMissing(['workflow', 'starter', 'tasks.assignedUser']);

        $data = (array) ($instance->data ?? []);
        $orderNo = 'BAK-' . date('Y') . '-' . str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d.m.Y');

        $completedTasks = $instance->tasks->where('status', 'completed');
        $sefiTask = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'şef') || str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'kabul'));
        $sefiName = $sefiTask?->completedBy?->name ?? ($sefiTask?->assignedUser?->name ?? 'Hakan DEMİR (Vardiya Şefi)');

        $pdf = Pdf::loadView('pdf.maintenance-order', [
            'instance'  => $instance,
            'data'      => $data,
            'orderNo'   => $orderNo,
            'issueDate' => $issueDate,
            'sefiName'  => $sefiName,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }
}
