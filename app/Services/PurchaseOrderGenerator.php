<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderGenerator
{
    /**
     * Acil Satın Alma & Malzeme Talep Süreci verilerinden resmi Satın Alma Talep Fişi PDF belgesi oluşturur.
     */
    public function generate(ProcessInstance $instance)
    {
        $instance->loadMissing(['workflow', 'starter', 'tasks.assignedUser']);

        $data = (array) ($instance->data ?? []);
        $orderNo = 'SAT-' . date('Y') . '-' . str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d.m.Y');

        $pdf = Pdf::loadView('pdf.purchase-order', [
            'instance'  => $instance,
            'data'      => $data,
            'orderNo'   => $orderNo,
            'issueDate' => $issueDate,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }
}
