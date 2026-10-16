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
        $instance->loadMissing(['workflow.formTemplate', 'starter', 'tasks.assignedUser', 'tasks.completedUser']);

        $data = (array) ($instance->data ?? []);
        $formTemplate = $instance->workflow?->formTemplate;
        $orderNo = 'DGB-' . date('Y') . '-' . str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d.m.Y');

        $documentNo = $formTemplate?->document_no ?: 'FR-IDR-008';
        $publishDate = $formTemplate?->publish_date ? date('d.m.Y', strtotime($formTemplate->publish_date)) : '25.09.2026';
        $revisionNo = $formTemplate?->revision_no ?: '01';
        $revisionDate = $formTemplate?->revision_date ? date('d.m.Y', strtotime($formTemplate->revision_date)) : $publishDate;
        $revisionInfo = $revisionNo . ' / ' . $revisionDate;
        $pageNo = '1 / 1';

        // Onaylayanların isimlerini ve tarihlerini bul
        $completedTasks = $instance->tasks->where('status', 'completed');
        
        $ilkAmirTask = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'ilk amir') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'ilk amir'));
        $ustAmirTask = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'üst amir') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'üst amir'));
        $gmyTask     = $completedTasks->first(fn($t) => str_contains(mb_strtolower($t->title ?? '', 'UTF-8'), 'gmy') || str_contains(mb_strtolower($t->node_label ?? '', 'UTF-8'), 'gmy'));

        $ilkAmirName = $ilkAmirTask?->completedUser?->name ?? ($ilkAmirTask?->assignedUser?->name ?? 'Birim Amiri');
        $ilkAmirDate = $ilkAmirTask?->completed_at ? date('d.m.Y H:i', strtotime($ilkAmirTask->completed_at)) : $issueDate;

        $ustAmirName = $ustAmirTask?->completedUser?->name ?? ($ustAmirTask?->assignedUser?->name ?? 'Departman Müdürü');
        $ustAmirDate = $ustAmirTask?->completed_at ? date('d.m.Y H:i', strtotime($ustAmirTask->completed_at)) : $issueDate;

        $gmyDate = $gmyTask?->completed_at ? date('d.m.Y H:i', strtotime($gmyTask->completed_at)) : null;
        $logoBase64 = CertificateGenerator::getLogoBase64();

        $pdf = Pdf::loadView('pdf.travel-order', [
            'instance'     => $instance,
            'data'         => $data,
            'orderNo'      => $orderNo,
            'issueDate'    => $issueDate,
            'documentNo'   => $documentNo,
            'publishDate'  => $publishDate,
            'revisionInfo' => $revisionInfo,
            'pageNo'       => $pageNo,
            'ilkAmirName'  => $ilkAmirName,
            'ilkAmirDate'  => $ilkAmirDate,
            'ustAmirName'  => $ustAmirName,
            'ustAmirDate'  => $ustAmirDate,
            'gmyDate'      => $gmyDate,
            'logoBase64'   => $logoBase64,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }
}
