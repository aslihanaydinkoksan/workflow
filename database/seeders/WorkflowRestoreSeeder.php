<?php

namespace Database\Seeders;

use App\Models\FormTemplate;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class WorkflowRestoreSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@local.test')->value('id')
            ?? User::where('email', 'admin@test.com')->value('id');
        $amirId = User::where('email', 'amir@local.test')->value('id')
            ?? User::where('email', 'amir@test.com')->value('id');
        $mudurId = User::where('email', 'mudur@local.test')->value('id')
            ?? User::where('email', 'mudur@test.com')->value('id');
        $maviYakaId = User::where('email', 'mavi_yaka@local.test')->value('id');
        $kullaniciRoleId = (string) Role::where('name', 'Kullanıcı')->value('id');

        // Test sırasında bozulan kayıtları kaldır
        Workflow::whereIn('name', [
            'TEST Rol ve Kullanici Atama',
            'TEST Yetkili Roller Havuzu',
        ])->delete();

        $this->restoreOrnekIzinOnay($adminId);
        $this->restoreAyakkabiSureci($kullaniciRoleId, $maviYakaId, $amirId);
        $this->restoreBilgisayarTalepFormu($adminId);
        $this->restoreBilgisayarTalepIslemi($adminId, $amirId);
        $this->restoreAdminTest($adminId);
        $this->restoreGorevDeneme($mudurId, $adminId);
        $this->restoreGggg($amirId);
        $this->restoreSiraylaTest();
        app(\App\Services\WorkflowRepairService::class)->repairAll(true);
    }

    private function restoreSiraylaTest(): void
    {
        $maviYakaRoleId = (string) Role::where('name', 'Mavi Yaka')->value('id');
        $mudurRoleId = (string) Role::where('name', 'Müdür')->value('id');
        $adminId = User::where('email', 'admin@local.test')->value('id');

        $workflow = Workflow::where('name', 'SIRAYLA TEST')->first();
        if (! $workflow) {
            return;
        }

        $normalizer = app(\App\Services\WorkflowGraphNormalizer::class);
        $normalized = $normalizer->normalize($workflow->nodes ?? [], $workflow->edges ?? []);

        $workflow->update([
            'status' => 'active',
            'nodes' => $normalized['nodes'],
            'edges' => $normalized['edges'],
            'allowed_roles' => array_values(array_unique(array_merge(
                $workflow->allowed_roles ?? [],
                ['1', $maviYakaRoleId]
            ))),
            'created_by' => $workflow->created_by ?? $adminId,
        ]);
    }

    private function resolveUserId(?int $id, int $fallbackId): int
    {
        if ($id && User::whereKey($id)->exists()) {
            return $id;
        }

        $legacyMap = [
            1687 => $fallbackId,
            1691 => User::where('email', 'mavi_yaka@local.test')->value('id') ?? $fallbackId,
            1692 => User::where('email', 'amir@local.test')->value('id') ?? $fallbackId,
        ];

        return $legacyMap[$id] ?? $fallbackId;
    }

    private function restoreOrnekIzinOnay(?int $adminId): void
    {
        $formId = FormTemplate::where('name', 'Deneme İzin Formu')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'Örnek İzin Onay Süreci'],
            [
                'description' => 'Personel izin talebi için örnek onay akışı.',
                'status' => 'active',
                'form_template_id' => $formId,
                'category' => ['İnsan Kaynakları'],
                'allowed_departments' => [],
                'allowed_roles' => [],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start_node', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Süreci Başlat', 'taskType' => 'start']],
                    ['id' => 'approval_node_1', 'type' => 'decision', 'position' => ['x' => 280, 'y' => 0], 'data' => ['label' => 'Yönetici Onayı', 'taskType' => 'approval', 'assignType' => 'hierarchy', 'assignValue' => 'manager_1']],
                    ['id' => 'end_node', 'type' => 'end', 'position' => ['x' => 560, 'y' => 0], 'data' => ['label' => 'Süreç Bitti', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'edge_1', 'source' => 'start_node', 'target' => 'approval_node_1'],
                    ['id' => 'edge_2', 'source' => 'approval_node_1', 'target' => 'end_node', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                ],
                'created_by' => $adminId,
            ]
        );
    }

    private function restoreAyakkabiSureci(string $kullaniciRoleId, ?int $depoUserId, ?int $depoAmiriId): void
    {
        $formId = FormTemplate::where('name', 'Depodan Malzeme Talep Formu (Ayakkabı)')->value('id');
        $depoUserId = $depoUserId ?: User::where('email', 'mavi_yaka@local.test')->value('id');
        $depoAmiriId = $depoAmiriId ?: User::where('email', 'amir@local.test')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'Ayakkabı Talep ve Onay Süreci'],
            [
                'description' => 'Vardiya amiri, müdür yardımcısı ve depo adımlarını içeren malzeme talep süreci.',
                'status' => 'active',
                'form_template_id' => $formId,
                'category' => ['Satın Alma'],
                'allowed_departments' => [],
                'allowed_roles' => [$kullaniciRoleId],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'node_start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Talep Başlatıldı', 'taskType' => 'start']],
                    ['id' => 'node_vardiya_amiri', 'type' => 'decision', 'position' => ['x' => 240, 'y' => 120], 'data' => ['label' => 'Vardiya Amiri Onayı', 'taskType' => 'approval', 'assignType' => 'hierarchy', 'assignValue' => 'manager_1']],
                    ['id' => 'node_mudur_yrd', 'type' => 'decision', 'position' => ['x' => 500, 'y' => 120], 'data' => ['label' => 'Müdür Yardımcısı Onayı', 'taskType' => 'approval', 'assignType' => 'hierarchy', 'assignValue' => 'manager_2']],
                    ['id' => 'node_depo_calisani', 'type' => 'decision', 'position' => ['x' => 760, 'y' => 60], 'data' => ['label' => 'Depo İşlemi', 'taskType' => 'approval', 'assignType' => 'user', 'assignValue' => (string) $depoUserId]],
                    ['id' => 'node_depo_amiri_bilgi', 'type' => 'task', 'position' => ['x' => 760, 'y' => 220], 'data' => ['label' => 'Depo Amirine Bilgi', 'taskType' => 'review', 'assignType' => 'user', 'assignValue' => (string) $depoAmiriId]],
                    ['id' => 'node_end', 'type' => 'end', 'position' => ['x' => 1020, 'y' => 120], 'data' => ['label' => 'Süreç Tamamlandı', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'edge_start_vardiya', 'source' => 'node_start', 'target' => 'node_vardiya_amiri'],
                    ['id' => 'edge_vardiya_mudur', 'source' => 'node_vardiya_amiri', 'target' => 'node_mudur_yrd', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'edge_vardiya_revize', 'source' => 'node_vardiya_amiri', 'target' => 'node_start', 'sourceHandle' => 'revised', 'data' => ['condition' => 'revised']],
                    ['id' => 'edge_mudur_depo', 'source' => 'node_mudur_yrd', 'target' => 'node_depo_calisani', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'edge_mudur_depo_bilgi', 'source' => 'node_mudur_yrd', 'target' => 'node_depo_amiri_bilgi', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'edge_depo_end', 'source' => 'node_depo_calisani', 'target' => 'node_end', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'edge_depo_revize', 'source' => 'node_depo_calisani', 'target' => 'node_start', 'sourceHandle' => 'revised', 'data' => ['condition' => 'revised']],
                    ['id' => 'edge_bilgi_end', 'source' => 'node_depo_amiri_bilgi', 'target' => 'node_end'],
                ],
                'created_by' => $depoAmiriId,
            ]
        );
    }

    private function restoreBilgisayarTalepFormu(?int $adminId): void
    {
        $formId = FormTemplate::where('name', 'TESTT')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'BİLGİSYARTALEPFORMU'],
            [
                'description' => 'Bilgisayar talep formu doldurma akışı.',
                'status' => 'active',
                'form_template_id' => $formId,
                'category' => ['Bilgi İşlem (IT)'],
                'allowed_departments' => [],
                'allowed_roles' => [],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Başlangıç', 'taskType' => 'start']],
                    ['id' => 'form_step', 'type' => 'task', 'position' => ['x' => 260, 'y' => 0], 'data' => ['label' => 'Form Görevi', 'taskType' => 'form', 'assignType' => 'starter']],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 520, 'y' => 0], 'data' => ['label' => 'Bitiş', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'form_step'],
                    ['id' => 'e2', 'source' => 'form_step', 'target' => 'end'],
                ],
                'created_by' => $adminId,
            ]
        );
    }

    private function restoreBilgisayarTalepIslemi(?int $adminId, ?int $amirId): void
    {
        $formId = FormTemplate::where('name', 'TESTT')->value('id');
        $onaylayanId = $this->resolveUserId(1687, $amirId);

        Workflow::updateOrCreate(
            ['name' => 'BİLGİSYARTALEPİŞLEMİ'],
            [
                'description' => 'Bilgisayar talebi onay, revize ve bilgilendirme adımları.',
                'status' => 'active',
                'form_template_id' => $formId,
                'category' => ['Bilgi İşlem (IT)'],
                'allowed_departments' => [],
                'allowed_roles' => [],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Başlangıç', 'taskType' => 'start']],
                    ['id' => 'form_step', 'type' => 'task', 'position' => ['x' => 220, 'y' => 120], 'data' => ['label' => 'Form Görevi', 'taskType' => 'form', 'assignType' => 'starter']],
                    ['id' => 'revize_form', 'type' => 'task', 'position' => ['x' => 220, 'y' => 280], 'data' => ['label' => 'Revize Formu', 'taskType' => 'form', 'assignType' => 'starter']],
                    ['id' => 'approval_step', 'type' => 'decision', 'position' => ['x' => 460, 'y' => 120], 'data' => ['label' => 'Onay Mekanizması', 'taskType' => 'approval', 'assignType' => 'user', 'assignValue' => (string) $onaylayanId]],
                    ['id' => 'info_step', 'type' => 'task', 'position' => ['x' => 700, 'y' => 220], 'data' => ['label' => 'İnceleme (Bilgi)', 'taskType' => 'review', 'assignType' => 'starter']],
                    ['id' => 'notify_step', 'type' => 'task', 'position' => ['x' => 700, 'y' => 40], 'data' => ['label' => 'Mail Bildirimi', 'taskType' => 'notify', 'notifyEmail' => 'bt@local.test']],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 940, 'y' => 120], 'data' => ['label' => 'Bitiş', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'form_step'],
                    ['id' => 'e2', 'source' => 'form_step', 'target' => 'approval_step'],
                    ['id' => 'e3', 'source' => 'approval_step', 'target' => 'revize_form', 'sourceHandle' => 'revised', 'data' => ['condition' => 'revised']],
                    ['id' => 'e4', 'source' => 'revize_form', 'target' => 'approval_step'],
                    ['id' => 'e5', 'source' => 'approval_step', 'target' => 'notify_step', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'e6', 'source' => 'approval_step', 'target' => 'start', 'sourceHandle' => 'rejected', 'data' => ['condition' => 'rejected']],
                    ['id' => 'e7', 'source' => 'notify_step', 'target' => 'info_step'],
                    ['id' => 'e8', 'source' => 'info_step', 'target' => 'end'],
                ],
                'created_by' => $adminId,
            ]
        );
    }

    private function restoreAdminTest(?int $adminId): void
    {
        $amirRoleId = (string) Role::where('name', 'Amir')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'ADMİN TEST'],
            [
                'description' => 'Admin test akışı: imza belgesi ve onay adımları.',
                'status' => 'active',
                'form_template_id' => null,
                'category' => ['İnsan Kaynakları'],
                'allowed_departments' => [],
                'allowed_roles' => collect(Role::pluck('id'))->map(fn ($id) => (string) $id)->all(),
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Başlangıç', 'taskType' => 'start']],
                    ['id' => 'sign_doc', 'type' => 'document', 'position' => ['x' => 280, 'y' => 40], 'data' => ['label' => 'İmza Belgesi', 'taskType' => 'document']],
                    ['id' => 'approve_sign', 'type' => 'task', 'position' => ['x' => 280, 'y' => 200], 'data' => ['label' => 'Onayla ve İmzala', 'taskType' => 'review', 'assignType' => 'role', 'assignValue' => $amirRoleId]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 560, 'y' => 120], 'data' => ['label' => 'Bitiş', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'sign_doc'],
                    ['id' => 'e2', 'source' => 'start', 'target' => 'approve_sign'],
                    ['id' => 'e3', 'source' => 'sign_doc', 'target' => 'end'],
                    ['id' => 'e4', 'source' => 'approve_sign', 'target' => 'end'],
                ],
                'created_by' => $adminId,
            ]
        );
    }

    private function restoreGorevDeneme(?int $mudurId, ?int $adminId): void
    {
        $formId = FormTemplate::where('name', 'TESTT')->value('id');
        $mudurRoleId = (string) Role::where('name', 'Müdür')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'görev deneme'],
            [
                'description' => 'Form, PDF, imza ve inceleme adımlarını test eden akış.',
                'status' => 'active',
                'form_template_id' => $formId,
                'category' => ['İnsan Kaynakları', 'Bilgi İşlem (IT)'],
                'allowed_departments' => [],
                'allowed_roles' => [
                    (string) Role::where('name', 'Admin')->value('id'),
                    (string) Role::where('name', 'Kullanıcı')->value('id'),
                    (string) $mudurRoleId,
                ],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Başlangıç', 'taskType' => 'start']],
                    ['id' => 'form_step', 'type' => 'task', 'position' => ['x' => 220, 'y' => 120], 'data' => ['label' => 'Form Görevi', 'taskType' => 'form', 'assignType' => 'starter']],
                    ['id' => 'pdf_step', 'type' => 'task', 'position' => ['x' => 440, 'y' => 120], 'data' => ['label' => 'PDF Üret', 'taskType' => 'review', 'assignType' => 'role', 'assignValue' => $mudurRoleId]],
                    ['id' => 'document_step', 'type' => 'document', 'position' => ['x' => 660, 'y' => 120], 'data' => ['label' => 'İmza Belgesi', 'taskType' => 'document']],
                    ['id' => 'review_step', 'type' => 'task', 'position' => ['x' => 880, 'y' => 120], 'data' => ['label' => 'İnceleme (Bilgi)', 'taskType' => 'review', 'assignType' => 'starter']],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 1100, 'y' => 120], 'data' => ['label' => 'Bitiş', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'form_step'],
                    ['id' => 'e2', 'source' => 'form_step', 'target' => 'pdf_step'],
                    ['id' => 'e3', 'source' => 'pdf_step', 'target' => 'document_step'],
                    ['id' => 'e4', 'source' => 'document_step', 'target' => 'review_step'],
                    ['id' => 'e5', 'source' => 'review_step', 'target' => 'end'],
                ],
                'created_by' => $adminId,
            ]
        );
    }

    private function restoreGggg(?int $amirId): void
    {
        $amirRoleId = (string) Role::where('name', 'Amir')->value('id');

        Workflow::updateOrCreate(
            ['name' => 'gggg'],
            [
                'description' => 'Eski taslak onay akışı (geri yüklendi).',
                'status' => 'draft',
                'form_template_id' => null,
                'category' => [],
                'allowed_departments' => [],
                'allowed_roles' => [$amirRoleId],
                'allowed_users' => [],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Başlangıç', 'taskType' => 'start']],
                    ['id' => 'approval_1', 'type' => 'decision', 'position' => ['x' => 260, 'y' => 120], 'data' => ['label' => '1. Onay', 'taskType' => 'approval', 'assignType' => 'role', 'assignValue' => $amirRoleId]],
                    ['id' => 'approval_2', 'type' => 'decision', 'position' => ['x' => 520, 'y' => 120], 'data' => ['label' => '2. Onay', 'taskType' => 'approval', 'assignType' => 'user', 'assignValue' => (string) $amirId]],
                    ['id' => 'form_step', 'type' => 'task', 'position' => ['x' => 780, 'y' => 120], 'data' => ['label' => 'Form Görevi', 'taskType' => 'form', 'assignType' => 'starter']],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 1040, 'y' => 120], 'data' => ['label' => 'Bitiş', 'taskType' => 'end']],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'approval_1'],
                    ['id' => 'e2', 'source' => 'approval_1', 'target' => 'approval_2', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'e3', 'source' => 'approval_2', 'target' => 'form_step', 'sourceHandle' => 'approved', 'data' => ['condition' => 'approved']],
                    ['id' => 'e4', 'source' => 'form_step', 'target' => 'end'],
                ],
                'created_by' => $amirId,
            ]
        );
    }
}
