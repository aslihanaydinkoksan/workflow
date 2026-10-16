<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use Barryvdh\DomPDF\Facade\Pdf;

class DofReportGenerator
{
    /**
     * Düzeltici ve Önleyici Faaliyet (DÖF / 8D) Süreci verilerinden resmi Kalite Raporu PDF belgesi oluşturur.
     */
    public function generate(ProcessInstance $instance)
    {
        $instance->loadMissing(['workflow.formTemplate', 'starter', 'tasks.assignedUser']);

        $data = (array) ($instance->data ?? []);
        $formTemplate = $instance->workflow?->formTemplate;
        $orderNo = 'DOF-' . date('Y') . '-' . str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d.m.Y');

        $documentNo = $formTemplate?->document_no ?: 'FR-KAL-015';
        $publishDate = $formTemplate?->publish_date ? date('d.m.Y', strtotime($formTemplate->publish_date)) : '13.10.2026';
        $revisionNo = $formTemplate?->revision_no ?: '04';
        $revisionDate = $formTemplate?->revision_date ? date('d.m.Y', strtotime($formTemplate->revision_date)) : $publishDate;
        $revisionInfo = $revisionNo . ' / ' . $revisionDate;
        $pageNo = '1 / 1';
        $logoBase64 = CertificateGenerator::getLogoBase64();

        $pdf = Pdf::loadView('pdf.dof-report', [
            'instance'     => $instance,
            'data'         => $data,
            'orderNo'      => $orderNo,
            'issueDate'    => $issueDate,
            'documentNo'   => $documentNo,
            'publishDate'  => $publishDate,
            'revisionInfo' => $revisionInfo,
            'pageNo'       => $pageNo,
            'logoBase64'   => $logoBase64,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }
}
