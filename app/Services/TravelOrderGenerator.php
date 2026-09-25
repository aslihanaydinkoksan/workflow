<?php

namespace App\Services;

use App\Models\ProcessInstance;
use Barryvdh\DomPDF\Facade\Pdf;

class TravelOrderGenerator
{
    /**
     * Dış Görev Süreci verilerinden resmi Seyahat İzin ve Görevlendirme Emri PDF belgesi oluşturur.
     */
    public function generate(ProcessInstance $instance)
    {
        $instance->loadMissing(['workflow', 'starter', 'tasks.assignedUser']);

        $data = (array) ($instance->data ?? []);
        $orderNo = 'DGB-' . date('Y') . '-' . str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d.m.Y');

        // Onaylayanların isimlerini ve tarihlerini bul
        $completedTasks = $instance->tasks->where('status', 'completed');
        
        $ilkAmirTask = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'ilk amir') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'ilk amir'));
        $ustAmirTask = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'üst amir') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'üst amir'));
        $gmyTask     = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'gmy') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'gmy'));

        $ilkAmirName = $ilkAmirTask?->completedBy?->name ?? ($ilkAmirTask?->assignedUser?->name ?? 'Birim Amiri');
        $ilkAmirDate = $ilkAmirTask?->completed_at ? date('d.m.Y H:i', strtotime($ilkAmirTask->completed_at)) : $issueDate;

        $ustAmirName = $ustAmirTask?->completedBy?->name ?? ($ustAmirTask?->assignedUser?->name ?? 'Departman Müdürü');
        $ustAmirDate = $ustAmirTask?->completed_at ? date('d.m.Y H:i', strtotime($ustAmirTask->completed_at)) : $issueDate;

        $gmyDate = $gmyTask?->completed_at ? date('d.m.Y H:i', strtotime($gmyTask->completed_at)) : null;

        $pdf = Pdf::loadView('pdf.travel-order', [
            'instance'     => $instance,
            'data'         => $data,
            'orderNo'      => $orderNo,
            'issueDate'    => $issueDate,
            'ilkAmirName'  => $ilkAmirName,
            'ilkAmirDate'  => $ilkAmirDate,
            'ustAmirName'  => $ustAmirName,
            'ustAmirDate'  => $ustAmirDate,
            'gmyDate'      => $gmyDate,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }
}
