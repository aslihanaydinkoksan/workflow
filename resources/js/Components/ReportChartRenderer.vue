<template>
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700/60 p-5 transition-all duration-200 hover:shadow-md flex flex-col justify-between h-full">
    <!-- Üst Başlık & Kontroller -->
    <div class="flex items-start justify-between gap-2 mb-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: primaryColor }"></span>
          <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base leading-snug">
            {{ widget.title }}
          </h3>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          {{ getDataSourceLabel(widget.data_source) }} · {{ widget.aggregation_type }}
        </p>
      </div>

      <div class="flex items-center gap-1.5">
        <!-- Grafik / Tablo Toggle -->
        <button
          v-if="widget.chart_type !== 'kpi_card'"
          type="button"
          @click="isTableView = !isTableView"
          :title="isTableView ? 'Grafik Görünümüne Geç' : 'Tablo Görünümüne Geç'"
          class="p-1.5 text-xs rounded-lg text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-colors"
        >
          <span v-if="!isTableView">📋</span>
          <span v-else>📊</span>
        </button>

        <!-- Ana Sayfaya Sabitleme Butonu -->
        <button
          type="button"
          @click="$emit('togglePin', widget)"
          :title="widget.is_pinned_to_home ? 'Ana Sayfa Sabitlemesini Kaldır' : 'Ana Sayfa Panosuna Sabitle'"
          class="p-1.5 text-xs rounded-lg transition-colors"
          :class="widget.is_pinned_to_home ? 'text-amber-600 hover:text-amber-700 bg-amber-50 dark:bg-amber-950/40 font-bold' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'"
        >
          <span>📌</span>
        </button>

        <!-- Silme Butonu (Opsiyonel) -->
        <button
          v-if="canManage"
          type="button"
          @click="$emit('delete', widget)"
          title="Widget'ı Kaldır"
          class="p-1.5 text-xs text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- İçerik Alanı -->
    <div class="flex-1 flex flex-col justify-center">
      <!-- 1. KPI KARTI GÖRÜNÜMÜ -->
      <div v-if="widget.chart_type === 'kpi_card'" class="py-4">
        <div class="flex items-baseline justify-between">
          <div class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            {{ formatNumber(chartData?.value ?? 0) }}
          </div>
          <span
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
            :class="[
              chartData?.value > 0
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
            ]"
          >
            Aktif Metrik
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
          {{ widget.dimension_field }} filtresine göre toplam hacim
        </p>
      </div>

      <!-- 2. TABLO GÖRÜNÜMÜ -->
      <div v-else-if="isTableView" class="overflow-x-auto max-h-[300px]">
        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 uppercase font-semibold text-[11px] sticky top-0">
            <tr>
              <th class="py-2 px-3">Kategori / Boyut</th>
              <th class="py-2 px-3 text-right">Değer</th>
              <th class="py-2 px-3 text-right">Pay (%)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="(row, idx) in tableData"
              :key="idx"
              class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40"
            >
              <td class="py-2 px-3 font-medium text-slate-800 dark:text-slate-200">
                {{ row.label }}
              </td>
              <td class="py-2 px-3 text-right font-bold text-slate-900 dark:text-white">
                {{ formatNumber(row.value) }}
              </td>
              <td class="py-2 px-3 text-right text-slate-500">
                {{ calculateShare(row.value) }}%
              </td>
            </tr>
            <tr v-if="!tableData.length">
              <td colspan="3" class="py-6 text-center text-slate-400">Veri bulunamadı</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 3. GRAFİK GÖRÜNÜMÜ (APEXCHARTS) -->
      <div v-else class="min-h-[260px] flex items-center justify-center">
        <div v-if="hasData" class="w-full">
          <!-- Donut / Pie Grafikler -->
          <apexchart
            v-if="['donut', 'pie'].includes(widget.chart_type)"
            width="100%"
            height="270"
            :type="widget.chart_type"
            :options="pieOptions"
            :series="chartData.series"
          />

          <!-- Bar / Line / Area Grafikler -->
          <apexchart
            v-else
            width="100%"
            height="270"
            :type="widget.chart_type === 'bar' ? 'bar' : (widget.chart_type === 'area' ? 'area' : 'line')"
            :options="barLineOptions"
            :series="chartData.series"
          />
        </div>

        <div v-else class="text-center py-10 text-slate-400 text-xs">
          <span class="text-2xl block mb-1">📭</span>
          Seçilen tarih aralığında bu metrik için veri kaydı bulunamadı.
        </div>
      </div>
    </div>

    <!-- Alt Bilgi Çubuğu -->
    <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700/40 flex items-center justify-between text-[11px] text-slate-400">
      <span>Tür: <strong class="uppercase text-slate-600 dark:text-slate-300">{{ widget.chart_type }}</strong></span>
      <span v-if="chartData?.total !== undefined">Toplam: <strong class="text-slate-700 dark:text-slate-200">{{ formatNumber(chartData.total) }}</strong></span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  widget: {
    type: Object,
    required: true,
  },
  chartData: {
    type: Object,
    default: () => ({ categories: [], series: [], total: 0 }),
  },
  canManage: {
    type: Boolean,
    default: true,
  },
});

defineEmits(['delete', 'togglePin']);

const isTableView = ref(false);

const primaryColor = computed(() => {
  return props.chartData?.colors?.[0] || '#6366F1';
});

const hasData = computed(() => {
  if (['donut', 'pie'].includes(props.widget.chart_type)) {
    return Array.isArray(props.chartData?.series) && props.chartData.series.length > 0 && props.chartData.series.some(v => v > 0);
  }
  return Array.isArray(props.chartData?.categories) && props.chartData.categories.length > 0;
});

const tableData = computed(() => {
  if (props.chartData?.table_rows) {
    return props.chartData.table_rows;
  }
  if (props.chartData?.categories && props.chartData?.series) {
    const vals = Array.isArray(props.chartData.series[0]?.data) 
      ? props.chartData.series[0].data 
      : props.chartData.series;
    return props.chartData.categories.map((cat, i) => ({
      label: cat,
      value: vals[i] || 0,
    }));
  }
  return [];
});

const calculateShare = (val) => {
  const tot = props.chartData?.total || 1;
  if (!tot) return '0.0';
  return ((val / tot) * 100).toFixed(1);
};

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0';
  return new Intl.NumberFormat('tr-TR').format(num);
};

const getDataSourceLabel = (ds) => {
  const map = {
    processes: 'Süreç Performansı',
    tasks: 'Görev & Kararlar',
    termin_sla: 'Termin & SLA',
    follow_ups: 'Numune Satış Takibi',
    form_fr_lvh_042: 'Levha Form Verisi',
  };
  return map[ds] || ds;
};

// Bar & Line ApexCharts Ayarları
const barLineOptions = computed(() => {
  const isDark = document.documentElement.classList.contains('dark');
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: 'Inter, sans-serif',
      foreColor: isDark ? '#94A3B8' : '#64748B',
    },
    colors: props.chartData?.colors || ['#6366F1'],
    plotOptions: {
      bar: {
        borderRadius: 6,
        columnWidth: '55%',
        distributed: false,
      },
    },
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: 'smooth',
      width: props.widget.chart_type === 'bar' ? 0 : 3,
    },
    xaxis: {
      categories: props.chartData?.categories || [],
      labels: {
        rotate: -30,
        style: { fontSize: '11px' },
        trim: true,
      },
    },
    yaxis: {
      labels: {
        formatter: (val) => formatNumber(val),
        style: { fontSize: '11px' },
      },
    },
    grid: {
      borderColor: isDark ? '#334155' : '#F1F5F9',
      strokeDashArray: 4,
    },
    tooltip: {
      theme: isDark ? 'dark' : 'light',
      y: {
        formatter: (val) => formatNumber(val),
      },
    },
  };
});

// Donut & Pie ApexCharts Ayarları
const pieOptions = computed(() => {
  const isDark = document.documentElement.classList.contains('dark');
  return {
    chart: {
      fontFamily: 'Inter, sans-serif',
    },
    labels: props.chartData?.categories || [],
    colors: props.chartData?.colors || ['#6366F1', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
    legend: {
      position: 'bottom',
      fontSize: '11px',
      labels: {
        colors: isDark ? '#94A3B8' : '#64748B',
      },
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val.toFixed(0)}%`,
    },
    tooltip: {
      theme: isDark ? 'dark' : 'light',
      y: {
        formatter: (val) => formatNumber(val),
      },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '68%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'Toplam',
              formatter: () => formatNumber(props.chartData?.total || 0),
            },
          },
        },
      },
    },
  };
});
</script>
