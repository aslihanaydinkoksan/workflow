<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SAP S/4HANA Entegrasyon Servisi
 * Numune takibinde siparişe dönüşen taleplerin SAP Satış Siparişi (Sales Order)
 * nesneleri ile eşleşmesini ve veri akışını yönetir.
 */
class SapIntegrationService
{
    /**
     * Siparişe dönen numune için SAP S/4HANA senkronizasyonunu başlatır.
     */
    public function syncSampleOrder(FollowUp $followUp): array
    {
        $instance = $followUp->processInstance;
        $formData = (array) ($instance?->data ?? []);

        // SAP Entegrasyon ayarlarını dinamik olarak veritabanından çek
        $isEnabled = (bool) Setting::get('sap_integration_enabled', false);
        $apiUrl = Setting::get('sap_api_url', '');
        $sapClient = Setting::get('sap_client', '100');
        $companyCode = Setting::get('sap_company_code', '1000');
        $salesOrg = Setting::get('sap_sales_org', '1000');

        // SAP S/4HANA OData / REST Uyumlu Payload Hazırlığı
        $customerName = $formData['musteri_adi'] ?? $formData['customer_name'] ?? ($instance?->starter?->name ?? 'Bilinmeyen Müşteri');
        $materialName = $formData['urun_adi'] ?? $formData['material_name'] ?? $formData['numune_adi'] ?? 'Numune Ürünü';
        $quantity = $formData['miktar'] ?? $formData['quantity'] ?? 1;

        $sapPayload = [
            'SalesOrderType'           => 'OR',
            'SalesOrganization'        => $salesOrg,
            'DistributionChannel'      => '10',
            'OrganizationDivision'     => '00',
            'SoldToParty'              => $customerName,
            'PurchaseOrderByCustomer'  => $followUp->order_number ?? ('NUM-' . $instance->id),
            'ReferenceWorkflowId'      => (string) $instance->id,
            'CustomerComment'          => $followUp->customer_feedback ?? 'Numune sürecinden siparişe dönüştürüldü.',
            'to_Item' => [
                [
                    'SalesOrderItem'       => '10',
                    'Material'             => $materialName,
                    'RequestedQuantity'    => (string) $quantity,
                    'ProductionPlant'      => '1000',
                    'StorageLocation'      => '0001'
                ]
            ],
            'Metadata' => [
                'System'       => 'SAP_S4HANA',
                'Client'       => $sapClient,
                'CompanyCode'  => $companyCode,
                'Source'       => 'KOKSAN_BPM_WORKFLOW',
                'Timestamp'    => now()->toIso8601String(),
            ]
        ];

        // Eğer SAP Entegrasyonu açık ve geçerli bir URL tanımlıysa gerçek API isteği gönder
        if ($isEnabled && !empty($apiUrl)) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                        'sap-client'   => $sapClient,
                    ])
                    ->post($apiUrl, $sapPayload);

                if ($response->successful()) {
                    $resData = $response->json();
                    $sapOrderId = $resData['d']['SalesOrder'] ?? $resData['SalesOrder'] ?? $followUp->order_number ?? ('SAP-' . rand(100000, 999999));

                    $followUp->update([
                        'sap_sales_order_id' => $sapOrderId,
                        'sap_sync_status'    => 'synced',
                        'sap_synced_at'       => now(),
                        'sap_payload'        => $sapPayload,
                        'sap_response'       => $resData,
                    ]);

                    Log::info("SAP S/4HANA Sipariş Başarıyla Eşleştirildi. Takip ID: {$followUp->id}, SAP No: {$sapOrderId}");

                    return [
                        'success'    => true,
                        'sap_order'  => $sapOrderId,
                        'message'    => 'SAP S/4HANA ile başarıyla senkronize edildi.',
                        'simulated'  => false,
                    ];
                } else {
                    $followUp->update([
                        'sap_sync_status' => 'failed',
                        'sap_payload'     => $sapPayload,
                        'sap_response'    => ['error' => $response->body(), 'status' => $response->status()],
                    ]);

                    Log::error("SAP S/4HANA Entegrasyon Hatası: Takip ID {$followUp->id}, Kod: {$response->status()}");

                    return [
                        'success'   => false,
                        'message'   => 'SAP S/4HANA yanıt vermedi (' . $response->status() . ').',
                        'simulated' => false,
                    ];
                }
            } catch (\Exception $e) {
                $followUp->update([
                    'sap_sync_status' => 'failed',
                    'sap_payload'     => $sapPayload,
                    'sap_response'    => ['exception' => $e->getMessage()],
                ]);

                Log::error("SAP S/4HANA İstek İstisnası: " . $e->getMessage());

                return [
                    'success'   => false,
                    'message'   => 'SAP sunucusuna erişilemedi: ' . $e->getMessage(),
                    'simulated' => false,
                ];
            }
        }

        // Simülasyon Modu (Geçiş aşamasında test ve hazır mimari için)
        $simulatedSapOrderId = $followUp->order_number ?: ('S4H-' . rand(50000000, 59999999));
        $simulatedResponse = [
            'status'             => 'SIMULATED_SUCCESS',
            'sap_sales_order'    => $simulatedSapOrderId,
            'client'             => $sapClient,
            'sales_org'          => $salesOrg,
            'synced_at'          => now()->toIso8601String(),
            'integration_status' => $isEnabled ? 'configured_but_empty_url' : 'simulation_mode_ready',
        ];

        $followUp->update([
            'sap_sales_order_id' => $simulatedSapOrderId,
            'sap_sync_status'    => 'synced',
            'sap_synced_at'       => now(),
            'sap_payload'        => $sapPayload,
            'sap_response'       => $simulatedResponse,
        ]);

        return [
            'success'   => true,
            'sap_order' => $simulatedSapOrderId,
            'message'   => 'SAP S/4HANA simülasyonu başarıyla kaydedildi.',
            'simulated' => true,
        ];
    }
}
