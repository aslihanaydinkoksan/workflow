<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rapor Widget'ları (Grafikler ve KPI Kartları)
        Schema::create('report_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('data_source', 64);          // 'processes', 'tasks', 'termin_sla', 'follow_ups', 'form_fr_lvh_042'
            $table->string('chart_type', 32);           // 'bar', 'line', 'area', 'donut', 'pie', 'kpi_card', 'table'
            $table->string('dimension_field', 128);     // Örn: 'department_name', 'status', 'created_month'
            $table->string('metric_field', 128)->default('id'); // Örn: 'id', 'duration_days', 'data.avans_tutari'
            $table->string('aggregation_type', 16)->default('COUNT'); // 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX'
            $table->json('filters_json')->nullable();   // Tarih filtresi, statü filtresi vb.
            $table->string('color_palette', 64)->default('indigo'); // 'indigo', 'emerald', 'amber', 'rose', 'blue'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Rapor Panoları / Dashboards
        Schema::create('report_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(true);
            $table->json('layout_grid_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Pano - Widget Ara Tablosu
        Schema::create('report_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('report_dashboards')->cascadeOnDelete();
            $table->foreignId('widget_id')->constrained('report_widgets')->cascadeOnDelete();
            $table->integer('order_index')->default(0);
            $table->integer('width_cols')->default(6); // 12'lik grid sistemi: 12 (tam boy), 6 (yarım), 4 (üçte bir)
            $table->timestamps();
        });

        // 4. Zamanlanmış Dağıtım & E-Posta Abonelikleri (Report Schedules)
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('dashboard_id')->nullable()->constrained('report_dashboards')->nullOnDelete();
            $table->foreignId('widget_id')->nullable()->constrained('report_widgets')->nullOnDelete();
            $table->string('frequency_type', 32)->default('weekly'); // 'daily', 'weekly', 'monthly', 'cron'
            $table->string('cron_expression', 64)->nullable();
            $table->time('dispatch_time')->default('08:30:00');
            $table->tinyInteger('day_of_week')->default(1); // 1 = Pazartesi
            $table->tinyInteger('day_of_month')->default(1);
            $table->json('recipients_json');            // ['aykut.celik@koksan.com', 'yonetim@koksan.com']
            $table->boolean('include_pdf')->default(true);
            $table->boolean('include_excel')->default(true);
            $table->boolean('include_html_body')->default(true);
            $table->json('filter_override_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Rapor Gönderim ve Yürütme Günlüğü (Audit Log)
        Schema::create('report_schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('report_schedules')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->string('status', 32);              // 'success', 'failed'
            $table->integer('recipients_count')->default(0);
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedule_logs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_dashboard_widgets');
        Schema::dropIfExists('report_dashboards');
        Schema::dropIfExists('report_widgets');
    }
};
