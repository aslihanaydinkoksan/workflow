<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportWidget;
use App\Models\ReportDashboard;
use App\Models\User;

class EnterpriseReportingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin ? $admin->id : 1;

        // 1. Widget Tanımları
        $w1 = ReportWidget::updateOrCreate(['title' => 'Süreç Durum Dağılımı'], [
            'data_source' => 'processes',
            'chart_type' => 'donut',
            'dimension_field' => 'status',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'indigo',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w2 = ReportWidget::updateOrCreate(['title' => 'Departman Bazlı Görev & Onay Yükü'], [
            'data_source' => 'tasks',
            'chart_type' => 'bar',
            'dimension_field' => 'department_name',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'emerald',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w3 = ReportWidget::updateOrCreate(['title' => 'İş Akışları Talep Hacmi'], [
            'data_source' => 'processes',
            'chart_type' => 'bar',
            'dimension_field' => 'workflow_name',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'blue',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w4 = ReportWidget::updateOrCreate(['title' => 'Onay & Karar Dağılımı (Onay / Ret / Revize)'], [
            'data_source' => 'tasks',
            'chart_type' => 'pie',
            'dimension_field' => 'decision',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'amber',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w5 = ReportWidget::updateOrCreate(['title' => 'Termin Gecikme Kök Neden Analitiği'], [
            'data_source' => 'termin_sla',
            'chart_type' => 'bar',
            'dimension_field' => 'reason_category',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'rose',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w6 = ReportWidget::updateOrCreate(['title' => 'Numune Satış Dönüşüm Durumu'], [
            'data_source' => 'follow_ups',
            'chart_type' => 'donut',
            'dimension_field' => 'status',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'emerald',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        $w7 = ReportWidget::updateOrCreate(['title' => 'Levha Numune Ürün Grubu Dağılımı (FR-LVH-042)'], [
            'data_source' => 'form_fr_lvh_042',
            'chart_type' => 'bar',
            'dimension_field' => 'data.urun_grubu',
            'metric_field' => 'id',
            'aggregation_type' => 'COUNT',
            'color_palette' => 'indigo',
            'created_by' => $adminId,
            'is_active' => true,
        ]);

        // 2. Dashboards (Panolar)
        $dash1 = ReportDashboard::updateOrCreate(['title' => '🏢 Kurumsal Yönetim & Süreç Performansı'], [
            'description' => 'Genel müdürlük ve direktörler için süreç hacimleri, departman iş yükü ve karar dağılımı',
            'is_default' => true,
            'is_public' => true,
            'created_by' => $adminId,
        ]);
        $dash1->widgets()->sync([
            $w1->id => ['order_index' => 1, 'width_cols' => 6],
            $w2->id => ['order_index' => 2, 'width_cols' => 6],
            $w3->id => ['order_index' => 3, 'width_cols' => 6],
            $w4->id => ['order_index' => 4, 'width_cols' => 6],
        ]);

        $dash2 = ReportDashboard::updateOrCreate(['title' => '🏭 İşletme, Kalite & Numune SLA Takibi'], [
            'description' => 'Fabrika işletme terminleri, kalite analizleri, gecikme kök nedenleri ve satış dönüşümü',
            'is_default' => false,
            'is_public' => true,
            'created_by' => $adminId,
        ]);
        $dash2->widgets()->sync([
            $w5->id => ['order_index' => 1, 'width_cols' => 6],
            $w6->id => ['order_index' => 2, 'width_cols' => 6],
            $w7->id => ['order_index' => 3, 'width_cols' => 12],
        ]);
    }
}
