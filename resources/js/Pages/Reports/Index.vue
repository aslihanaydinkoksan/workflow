<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center p-2 rounded-xl bg-indigo-600/10 text-indigo-600 text-xl font-bold">
              📊
            </span>
            <div>
              <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                Kurumsal İş Zekası & Raporlama Kokpiti
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  Power BI Modülü
                </span>
              </h1>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Tüm modüllerden, süreçlerden ve form verilerinden anlık grafikler ve zamanlanmış e-posta dağıtımları
              </p>
            </div>
          </div>
        </div>

        <!-- Üst Eylem Butonları -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Excel Dışa Aktar -->
          <a
            v-if="activeDashboard"
            :href="route('reports.export.excel', { dashboard: activeDashboard.id, date_range: selectedDateRange, start_date: startDate, end_date: endDate, department_id: selectedDepartmentId || undefined, workflow_id: selectedWorkflowId || undefined })"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 transition-colors shadow-xs"
            title="Tüm panoyu Excel (.xlsx) olarak indir"
          >
            <span>📥</span> Excel İndir
          </a>

          <!-- PDF Dışa Aktar -->
          <a
            v-if="activeDashboard"
            :href="route('reports.export.pdf', { dashboard: activeDashboard.id, date_range: selectedDateRange, start_date: startDate, end_date: endDate, department_id: selectedDepartmentId || undefined, workflow_id: selectedWorkflowId || undefined })"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 transition-colors shadow-xs"
            title="Resmi Köksan formatında PDF dökümü al"
          >
            <span>📄</span> PDF Rapor
          </a>

          <!-- E-Posta Aboneliği & Zamanlayıcı -->
          <button
            type="button"
            @click="showScheduleModal = true"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-xl hover:bg-indigo-100 transition-colors shadow-xs"
          >
            <span>📧</span> Zamanlanmış & Anlık Mail ({{ schedules.length }})
          </button>

          <!-- Yeni Pano Oluştur -->
          <button
            type="button"
            @click="showNewDashboardModal = true"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 transition-colors shadow-xs"
          >
            <span>📁</span> Yeni Pano
          </button>

          <!-- Yeni Grafik / Widget Sihirbazı -->
          <button
            type="button"
            @click="openNewWidgetModal"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-all shadow-sm hover:shadow-indigo-500/20"
          >
            <span>✨</span> Yeni Grafik / Metrik
          </button>
        </div>
      </div>
    </template>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <!-- 1. PANO GEZİNİM SEKME ÇUBUĞU (DASHBOARD TABS) -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-2.5 shadow-xs border border-slate-200 dark:border-slate-700/60 flex items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 overflow-x-auto py-0.5">
          <button
            v-for="dash in dashboards"
            :key="dash.id"
            type="button"
            @click="switchDashboard(dash.id)"
            class="group inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl whitespace-nowrap transition-all"
            :class="[
              activeDashboard?.id === dash.id
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
            ]"
          >
            <span>{{ dash.is_default ? '⭐' : '📊' }}</span>
            <span>{{ dash.title }}</span>
            <span
              v-if="activeDashboard?.id === dash.id"
              class="ml-0.5 text-[10px] px-1.5 py-0.2 bg-white/20 rounded-full font-semibold"
            >
              {{ activeWidgets.length }}
            </span>
          </button>
        </div>

        <!-- Yeni Pano Butonu -->
        <button
          type="button"
          @click="showNewDashboardModal = true"
          class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-700/60 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 transition-colors"
        >
          <span>➕</span> Yeni Pano Ekle
        </button>
      </div>

      <!-- 2. KÜRESEL FİLTRELEME & DİLİMLER ÇUBUĞU (GLOBAL FILTER & SLICER RIBBON) -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-3 shadow-xs border border-slate-200 dark:border-slate-700/60">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <!-- Sol: Filtre Başlığı & Durum İndikatörü -->
          <div class="flex items-center gap-2 shrink-0">
            <div class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-bold text-xs flex items-center gap-1.5">
              <span>⚡</span>
              <span class="tracking-wide uppercase text-[11px] font-extrabold">Veri Dilimleyicileri</span>
            </div>
            <span v-if="activeFilterCount > 0" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
              {{ activeFilterCount }} Filtre Aktif
            </span>
            <span v-else class="text-[11px] text-slate-400 dark:text-slate-500 hidden sm:inline">
              Tüm panodaki grafikleri anlık dilimler
            </span>
          </div>

          <!-- Sağ: Ferah Filtre Elemanları -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Departman -->
            <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 px-2.5 py-1.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
              <span class="text-slate-400 font-medium">🏢 Departman:</span>
              <select
                v-model="selectedDepartmentId"
                @change="applyGlobalFilters"
                class="text-xs py-1 px-2 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium focus:ring-indigo-500 shadow-2xs min-w-[150px] max-w-[200px]"
              >
                <option value="">Tüm Departmanlar</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                  {{ dept.name }}
                </option>
              </select>
            </div>

            <!-- İş Akışı -->
            <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 px-2.5 py-1.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
              <span class="text-slate-400 font-medium">🚀 İş Akışı:</span>
              <select
                v-model="selectedWorkflowId"
                @change="applyGlobalFilters"
                class="text-xs py-1 px-2 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium focus:ring-indigo-500 shadow-2xs min-w-[170px] max-w-[230px]"
              >
                <option value="">Tüm İş Akışları</option>
                <option v-for="wf in workflows" :key="wf.id" :value="wf.id">
                  {{ wf.name }}
                </option>
              </select>
            </div>

            <!-- Tarih Aralığı (Takvim) -->
            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 px-2.5 py-1.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
              <span class="text-slate-400 font-medium">📅</span>
              <input
                type="date"
                v-model="startDate"
                @change="applyCalendarDateRange"
                placeholder="Başlangıç"
                title="Başlangıç Tarihi"
                class="text-xs py-1 px-2 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-indigo-500 shadow-2xs"
              />
              <span class="text-slate-400">-</span>
              <input
                type="date"
                v-model="endDate"
                @change="applyCalendarDateRange"
                placeholder="Bitiş"
                title="Bitiş Tarihi"
                class="text-xs py-1 px-2 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-indigo-500 shadow-2xs"
              />

              <!-- Presetler Dropdown -->
              <select
                v-model="selectedDateRange"
                @change="applyPresetRange"
                class="text-xs py-1 px-2 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium focus:ring-indigo-500 shadow-2xs"
              >
                <option value="custom" disabled v-if="startDate || endDate">Özel Aralık</option>
                <option value="all_time">Tüm Zamanlar</option>
                <option value="last_30_days">Son 30 Gün</option>
                <option value="this_month">Bu Ay</option>
                <option value="this_week">Bu Hafta</option>
                <option value="today">Bugün</option>
                <option value="this_year">Bu Yıl</option>
              </select>
            </div>

            <!-- Filtreleri Temizle Butonu -->
            <button
              v-if="activeFilterCount > 0"
              type="button"
              @click="clearAllFilters"
              title="Tüm filtreleri temizle"
              class="px-3 py-1.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-xl transition-all shadow-2xs flex items-center gap-1"
            >
              <span>✕</span> Filtreleri Sıfırla
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Pano Açıklaması ve Üst Bilgi Kartı -->
      <div v-if="activeDashboard" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-4 rounded-2xl shadow-sm border border-slate-800">
        <div>
          <h2 class="text-base font-bold flex flex-wrap items-center gap-2">
            {{ activeDashboard.title }}
            <span v-if="activeDashboard.is_default" class="text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-500/30">
              Varsayılan
            </span>
            <span v-if="startDate && endDate" class="text-[10px] font-medium bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded-full border border-indigo-500/30">
              📅 {{ formatDate(startDate) }} - {{ formatDate(endDate) }}
            </span>
            <span v-if="selectedDepartmentName" class="text-[10px] font-medium bg-amber-500/20 text-amber-300 px-2 py-0.5 rounded-full border border-amber-500/30 flex items-center gap-1">
              🏢 {{ selectedDepartmentName }}
            </span>
            <span v-if="selectedWorkflowName" class="text-[10px] font-medium bg-cyan-500/20 text-cyan-300 px-2 py-0.5 rounded-full border border-cyan-500/30 flex items-center gap-1">
              🚀 {{ selectedWorkflowName }}
            </span>
          </h2>
          <p class="text-xs text-slate-300 mt-0.5">
            {{ activeDashboard.description || 'Bu panodaki tüm grafikler canlı veritabanı sorgularından üretilmektedir.' }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="text-right text-xs text-slate-400 hidden sm:block mr-2">
            Aktif Grafik: <strong class="text-white">{{ activeWidgets.length }} adet</strong>
          </div>
          <!-- Pano Düzenle Butonu -->
          <button
            type="button"
            @click="openEditDashboardModal"
            class="px-2.5 py-1 text-xs font-semibold bg-white/10 hover:bg-white/20 text-white rounded-xl border border-white/15 transition-colors flex items-center gap-1.5 shadow-2xs"
            title="Pano Başlığı ve Açıklamasını Düzenle"
          >
            <span>✏️</span> Düzenle
          </button>
          <!-- Pano Sil Butonu -->
          <button
            v-if="dashboards.length > 1"
            type="button"
            @click="openDeleteDashboardModal"
            class="px-2.5 py-1 text-xs font-semibold bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 rounded-xl border border-rose-500/30 transition-colors flex items-center gap-1.5 shadow-2xs"
            title="Bu Rapor Panosunu Sil"
          >
            <span>🗑️</span> Sil
          </button>
        </div>
      </div>

      <!-- 3. GRAFİK & WIDGET GRID ALANI (12 Kolonluk Dinamik Grid) -->
      <div v-if="activeWidgets.length > 0" class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <div
          v-for="widget in activeWidgets"
          :key="widget.id"
          :class="[
            widget.pivot?.width_cols === 12 ? 'col-span-12' : (widget.pivot?.width_cols === 4 ? 'md:col-span-4 col-span-12' : 'md:col-span-6 col-span-12')
          ]"
        >
          <ReportChartRenderer
            :widget="widget"
            :chart-data="widget.chart_data"
            @delete="deleteWidget(widget)"
            @togglePin="handleTogglePin(widget)"
          />
        </div>
      </div>

      <!-- Boş Pano Durumu -->
      <div v-else class="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700">
        <span class="text-4xl block mb-2">📊</span>
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Bu Panoda Henüz Grafik Tanımlanmamış</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
          Power BI sihirbazını kullanarak süreçlerden veya form verilerinden anında yeni bir grafik veya KPI kartı oluşturun.
        </p>
        <button
          type="button"
          @click="openNewWidgetModal"
          class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
        >
          <span>✨</span> İlk Grafiği Oluştur
        </button>
      </div>
    </div>

    <!-- MODAL 1: POWER BI STİLİ NO-CODE WIDGET / GRAFİK SİHİRBAZI -->
    <Modal :show="showWidgetModal" @close="showWidgetModal = false" max-width="3xl">
      <div class="p-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3 mb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>✨</span> Power BI Rapor & Grafik Sihirbazı
            </h3>
            <p class="text-xs text-slate-500">
              İstediğiniz veri kaynağını, eksenleri ve grafik türünü seçin; anlık önizleme ile kaydedin
            </p>
          </div>
          <button type="button" @click="showWidgetModal = false" class="text-slate-400 hover:text-slate-600 text-lg">
            ✕
          </button>
        </div>

        <form @submit.prevent="saveWidget" class="space-y-4">
          <!-- Başlık & Pano Hedefi -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Grafik / Metrik Başlığı *</label>
              <input
                v-model="widgetForm.title"
                type="text"
                required
                placeholder="Örn: Aylık Talep Dağılımı"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Genişlik (Grid)</label>
              <select
                v-model="widgetForm.width_cols"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option :value="6">Yarım Genişlik (6 Kolon - Yan Yana 2 Grafik)</option>
                <option :value="12">Tam Genişlik (12 Kolon - Tam Sayfa)</option>
                <option :value="4">Üçte Bir (4 Kolon - Yan Yana 3 Grafik)</option>
              </select>
            </div>
          </div>

          <!-- ADIM 1: Veri Kaynağı Seçimi (Çekirdek Sistem + Dinamik Keşfedilen Formlar) -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">1. Veri Kaynağı Seçimi *</label>
              <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                ⚡ Tüm Formlar (Preform, Kapak, Levha vb.) Otomatik Keşfedilir
              </span>
            </div>

            <!-- Çekirdek Sistem Kaynakları -->
            <div class="text-[11px] font-bold uppercase text-slate-400 mt-2 mb-1">Çekirdek Süreç Modülleri</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 mb-3">
              <button
                v-for="(meta, key) in coreDataSources"
                :key="key"
                type="button"
                @click="selectDataSource(key)"
                class="p-2.5 text-left rounded-xl border transition-all text-xs"
                :class="[
                  widgetForm.data_source === key
                    ? 'border-indigo-600 bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 font-bold shadow-xs'
                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300'
                ]"
              >
                <div class="truncate font-semibold">{{ meta.label }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 truncate">{{ meta.description }}</div>
              </button>
            </div>

            <!-- Dinamik Form Şablonları (Otomatik Listelenen) -->
            <div class="text-[11px] font-bold uppercase text-slate-400 mt-2 mb-1 flex items-center justify-between">
              <span>Dinamik Form Şablonları ({{ dynamicFormDataSourcesCount }} adet)</span>
              <span class="text-[10px] font-normal lowercase text-slate-500">yeni şablon eklendiğinde anında burada listelenir</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-1 border border-slate-100 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-900/20">
              <button
                v-for="(meta, key) in dynamicFormDataSources"
                :key="key"
                type="button"
                @click="selectDataSource(key)"
                class="p-2 text-left rounded-xl border transition-all text-xs"
                :class="[
                  widgetForm.data_source === key
                    ? 'border-indigo-600 bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 font-bold shadow-xs'
                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800'
                ]"
              >
                <div class="truncate font-medium">{{ meta.label }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 truncate">{{ meta.dimensions?.length || 0 }} boyut · {{ meta.metrics?.length || 0 }} metrik</div>
              </button>
            </div>
          </div>

          <!-- ADIM 2: Grafik Türü Seçimi -->
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">2. Görselleştirme / Grafik Türü *</label>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
              <button
                v-for="ct in chartTypes"
                :key="ct.id"
                type="button"
                @click="widgetForm.chart_type = ct.id; triggerPreview()"
                class="p-2 text-center rounded-xl border transition-all text-xs"
                :class="[
                  widgetForm.chart_type === ct.id
                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold'
                    : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300'
                ]"
              >
                <span class="text-lg block">{{ ct.icon }}</span>
                <span class="text-[11px] mt-0.5 block">{{ ct.label }}</span>
              </button>
            </div>
          </div>

          <!-- ADIM 3: Eksenler & Metrikler (Power BI X / Y Mapping) -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
            <!-- X Ekseni (Dimension) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">X Ekseni (Kategori / Boyut) *</label>
              <select
                v-model="widgetForm.dimension_field"
                @change="triggerPreview"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 focus:ring-indigo-500"
              >
                <option v-for="dim in availableDimensions" :key="dim.field" :value="dim.field">
                  {{ dim.label }}
                </option>
              </select>
            </div>

            <!-- Y Ekseni (Metric) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Y Ekseni (Metrik / Sayısal) *</label>
              <select
                v-model="widgetForm.metric_field"
                @change="triggerPreview"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 focus:ring-indigo-500"
              >
                <option v-for="m in availableMetrics" :key="m.field" :value="m.field">
                  {{ m.label }}
                </option>
              </select>
            </div>

            <!-- Aggregation (Hesaplama) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Hesaplama Yöntemi *</label>
              <select
                v-model="widgetForm.aggregation_type"
                @change="triggerPreview"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 focus:ring-indigo-500"
              >
                <option value="COUNT">COUNT (Kayıt Adedi)</option>
                <option value="SUM">SUM (Toplam Tutar / Değer)</option>
                <option value="AVG">AVG (Ortalama Değer)</option>
                <option value="MAX">MAX (En Yüksek Değer)</option>
                <option value="MIN">MIN (En Düşük Değer)</option>
              </select>
            </div>
          </div>

          <!-- Renk Paleti -->
          <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Renk Paleti:</span>
            <div class="flex items-center gap-2">
              <button
                v-for="pal in palettes"
                :key="pal.id"
                type="button"
                @click="widgetForm.color_palette = pal.id; triggerPreview()"
                class="px-2.5 py-1 text-xs rounded-lg font-medium border transition-all"
                :class="[
                  widgetForm.color_palette === pal.id
                    ? 'border-indigo-600 bg-white font-bold ring-2 ring-indigo-500/20'
                    : 'border-transparent text-slate-600 hover:bg-slate-100'
                ]"
              >
                <span class="inline-block w-2.5 h-2.5 rounded-full mr-1.5" :style="{ backgroundColor: pal.color }"></span>
                {{ pal.label }}
              </button>
            </div>
          </div>

          <!-- CANLI ÖNİZLEME ALANI (LIVE PREVIEW) -->
          <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-3 bg-white dark:bg-slate-800">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Canlı Grafik Önizlemesi</span>
              <span v-if="isPreviewLoading" class="text-xs text-indigo-500 animate-pulse">Yükleniyor...</span>
            </div>
            <div v-if="previewChartData" class="max-h-[220px] overflow-hidden">
              <ReportChartRenderer
                :widget="{ title: widgetForm.title || 'Önizleme', chart_type: widgetForm.chart_type, data_source: widgetForm.data_source, aggregation_type: widgetForm.aggregation_type }"
                :chart-data="previewChartData"
                :can-manage="false"
              />
            </div>
            <div v-else class="text-center py-6 text-slate-400 text-xs">
              Önizleme oluşturmak için seçimleri tamamlayın
            </div>
          </div>

          <!-- Alt Butonlar -->
          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
            <button
              type="button"
              @click="showWidgetModal = false"
              class="px-3.5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
            >
              İptal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm disabled:opacity-50"
            >
              {{ isSubmitting ? 'Kaydediliyor...' : 'Panoya Ekle ve Kaydet' }}
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- MODAL 2: YENİ PANO OLUŞTURMA MODALI -->
    <Modal :show="showNewDashboardModal" @close="showNewDashboardModal = false" max-width="md">
      <div class="p-6">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">📁 Yeni Rapor Panosu Oluştur</h3>
        <form @submit.prevent="saveDashboard" class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Pano Başlığı *</label>
            <input
              v-model="dashboardForm.title"
              type="text"
              required
              placeholder="Örn: Finans & Bütçe Yönetimi"
              class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Açıklama</label>
            <textarea
              v-model="dashboardForm.description"
              rows="2"
              placeholder="Bu panonun amacı ve hedef kitlesi..."
              class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
            ></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input
              v-model="dashboardForm.is_default"
              type="checkbox"
              id="is_default_check"
              class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <label for="is_default_check" class="text-xs text-slate-700">Girişte varsayılan pano olarak açılsın</label>
          </div>
          <div class="flex justify-end gap-2 pt-2 border-t">
            <button type="button" @click="showNewDashboardModal = false" class="px-3 py-1.5 text-xs text-slate-600">İptal</button>
            <button type="submit" class="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 rounded-xl">Oluştur</button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- MODAL 2.1: PANO DÜZENLEME MODALI (EDIT / UPDATE) -->
    <Modal :show="showEditDashboardModal" @close="showEditDashboardModal = false" max-width="md">
      <div class="p-6">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">✏️ Rapor Panosunu Düzenle</h3>
        <form @submit.prevent="saveEditDashboard" class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Pano Başlığı *</label>
            <input
              v-model="editDashboardForm.title"
              type="text"
              required
              class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Açıklama</label>
            <textarea
              v-model="editDashboardForm.description"
              rows="2"
              class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            ></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input
              v-model="editDashboardForm.is_default"
              type="checkbox"
              id="edit_is_default_check"
              class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <label for="edit_is_default_check" class="text-xs text-slate-700 dark:text-slate-300">Girişte varsayılan pano olarak açılsın</label>
          </div>
          <div class="flex justify-end gap-2 pt-2 border-t dark:border-slate-700">
            <button type="button" @click="showEditDashboardModal = false" class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400">İptal</button>
            <button type="submit" :disabled="isUpdatingDashboard" class="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors disabled:opacity-50">
              {{ isUpdatingDashboard ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet' }}
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- MODAL 2.2: PANO SİLME ONAY MODALI (DELETE) -->
    <Modal :show="showDeleteDashboardModal" @close="showDeleteDashboardModal = false" max-width="md">
      <div class="p-6">
        <div class="flex items-start gap-3">
          <div class="p-2.5 bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 rounded-xl shrink-0">
            <span class="text-xl">⚠️</span>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Rapor Panosunu Sil</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              <strong>"{{ activeDashboard?.title }}"</strong> panosunu ve içinde yer alan <strong>{{ activeWidgets.length }} adet grafiği</strong> kalıcı olarak silmek istediğinize emin misiniz?
            </p>
            <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-2 bg-amber-50 dark:bg-amber-950/30 p-2 rounded-lg border border-amber-200 dark:border-amber-800">
              ℹ️ Pano silindiğinde diğer mevcut panolarınız aktif kalmaya devam eder.
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-4 mt-4 border-t dark:border-slate-700">
          <button type="button" @click="showDeleteDashboardModal = false" class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400">İptal</button>
          <button
            type="button"
            @click="confirmDeleteDashboard"
            :disabled="isDeletingDashboard"
            class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors disabled:opacity-50"
          >
            {{ isDeletingDashboard ? 'Siliniyor...' : 'Evet, Panoyu Kalıcı Olarak Sil' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- MODAL 3: ZAMANLANMIŞ & ANLIK E-POSTA DAĞITIM PLANI MODALI -->
    <Modal :show="showScheduleModal" @close="showScheduleModal = false" max-width="2xl">
      <div class="p-6">
        <div class="flex items-center justify-between border-b pb-3 mb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>📧</span> Otomatik & Anlık E-Posta Rapor Dağıtım Planlayıcısı
            </h3>
            <p class="text-xs text-slate-500">
              Bu panodaki verileri anlık olarak tek tıkla veya belirlenen periyotlarda PDF ve Excel ekleriyle ilgililere otomatik e-posta atar
            </p>
          </div>
          <button type="button" @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <!-- Mevcut Planlar Listesi -->
        <div v-if="schedules.length > 0" class="mb-5 space-y-2">
          <h4 class="text-xs font-bold uppercase text-slate-400">Aktif Gönderim Planları</h4>
          <div
            v-for="sc in schedules"
            :key="sc.id"
            class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3 text-xs"
          >
            <div>
              <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                {{ sc.title }}
                <span
                  class="text-[10px] px-1.5 py-0.2 rounded-full font-bold uppercase"
                  :class="sc.frequency_type === 'instant' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'"
                >
                  {{ sc.frequency_type === 'instant' ? '⚡ Anlık Gönderim' : sc.frequency_type }}
                </span>
              </div>
              <div class="text-[11px] text-slate-500 mt-0.5">
                Alıcılar: {{ sc.recipients_json?.join(', ') }}
                <span v-if="sc.frequency_type !== 'instant'">· Gönderim Saati: <strong>{{ sc.dispatch_time }}</strong></span>
              </div>
              <div v-if="sc.last_sent_at" class="text-[10px] text-emerald-600 mt-0.5">
                Son Başarılı Gönderim: {{ formatDate(sc.last_sent_at) }}
              </div>
            </div>

            <!-- Anında Test Gönder Butonu -->
            <button
              type="button"
              @click="sendScheduleNow(sc)"
              :disabled="sendingScheduleId === sc.id"
              class="px-2.5 py-1 text-[11px] font-bold text-indigo-700 bg-indigo-100 hover:bg-indigo-200 rounded-lg transition-colors shrink-0 disabled:opacity-50"
            >
              {{ sendingScheduleId === sc.id ? 'Gönderiliyor...' : '⚡ Şimdi Tekrar Gönder' }}
            </button>
          </div>
        </div>

        <!-- Yeni Gönderim / Rapor Dağıtımı Ekleme Formu -->
        <form @submit.prevent="saveSchedule" class="space-y-3 border-t pt-4">
          <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">➕ Yeni E-Posta Rapor Gönderimi Tanımla</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Gönderim Başlığı *</label>
              <input
                v-model="scheduleForm.title"
                type="text"
                required
                placeholder="Örn: Anlık Kalite ve Üretim Raporu"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Gönderim Sıklığı *</label>
              <select
                v-model="scheduleForm.frequency_type"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-semibold text-slate-800 dark:text-slate-200"
              >
                <option value="instant">⚡ Anlık (Hemen Şimdi Gönder)</option>
                <option value="daily">Hergün (Daily)</option>
                <option value="weekly">Haftalık (Her Pazartesi)</option>
                <option value="monthly">Aylık (Her Ayın 1'i)</option>
              </select>
            </div>
          </div>

          <!-- ANLIK MOD BİLGİLENDİRME BANNERI -->
          <div v-if="scheduleForm.frequency_type === 'instant'" class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2">
            <span class="text-base">⚡</span>
            <div>
              <strong>Anlık Gönderim:</strong> Bu seçenek seçildiğinde cron beklenmez. Form kaydedildiği an bu panonun güncel durumu PDF ve Excel ekleriyle birlikte aşağıdaki e-posta adreslerine derhal iletilir.
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-if="scheduleForm.frequency_type !== 'instant'">
              <label class="block text-xs font-bold text-slate-700 mb-1">Gönderim Saati *</label>
              <input
                v-model="scheduleForm.dispatch_time"
                type="time"
                required
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
              />
            </div>
            <div :class="scheduleForm.frequency_type === 'instant' ? 'sm:col-span-2' : ''">
              <label class="block text-xs font-bold text-slate-700 mb-1">Alıcı E-Postaları (Virgülle ayırın) *</label>
              <input
                v-model="scheduleForm.recipients"
                type="text"
                required
                placeholder="aykut.celik@koksan.com, yonetim@koksan.com"
                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
              />
            </div>
          </div>

          <div class="flex items-center gap-4 text-xs">
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input v-model="scheduleForm.include_pdf" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
              <span>Resmi PDF Raporu Ekle</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input v-model="scheduleForm.include_excel" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
              <span>Veri Tablosu Excel (.xlsx) Ekle</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t">
            <button type="button" @click="showScheduleModal = false" class="px-3 py-1.5 text-xs text-slate-600">Kapat</button>
            <button
              type="submit"
              :disabled="isSubmittingSchedule"
              class="px-4 py-1.5 text-xs font-bold text-white rounded-xl shadow-xs transition-colors"
              :class="scheduleForm.frequency_type === 'instant' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700'"
            >
              {{ isSubmittingSchedule ? 'Gönderiliyor...' : (scheduleForm.frequency_type === 'instant' ? '⚡ Şimdi Gönder ve Kaydet' : 'Planı Kaydet') }}
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportChartRenderer from '@/Components/ReportChartRenderer.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  dashboards: { type: Array, default: () => [] },
  activeDashboard: { type: Object, default: null },
  activeWidgets: { type: Array, default: () => [] },
  allWidgets: { type: Array, default: () => [] },
  dataSourcesMeta: { type: Object, default: () => ({}) },
  schedules: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  workflows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

// Doğrudan Takvim Aralığı ve Presets
const selectedDateRange = ref(props.filters?.date_range || 'all_time');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const selectedDepartmentId = ref(props.filters?.department_id ? String(props.filters.department_id) : '');
const selectedWorkflowId = ref(props.filters?.workflow_id ? String(props.filters.workflow_id) : '');

const selectedDepartmentName = computed(() => {
  if (!selectedDepartmentId.value) return null;
  const dept = props.departments.find(d => String(d.id) === String(selectedDepartmentId.value));
  return dept ? dept.name : null;
});

const selectedWorkflowName = computed(() => {
  if (!selectedWorkflowId.value) return null;
  const wf = props.workflows.find(w => String(w.id) === String(selectedWorkflowId.value));
  return wf ? wf.name : null;
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (selectedDepartmentId.value) count++;
  if (selectedWorkflowId.value) count++;
  if (startDate.value || endDate.value || (selectedDateRange.value && selectedDateRange.value !== 'all_time')) count++;
  return count;
});

const showWidgetModal = ref(false);
const showNewDashboardModal = ref(false);
const showEditDashboardModal = ref(false);
const showDeleteDashboardModal = ref(false);
const showScheduleModal = ref(false);
const isSubmitting = ref(false);
const isSubmittingSchedule = ref(false);
const isUpdatingDashboard = ref(false);
const isDeletingDashboard = ref(false);
const isPreviewLoading = ref(false);
const sendingScheduleId = ref(null);

const editDashboardForm = ref({
  title: '',
  description: '',
  is_default: false,
});

// Form Nesneleri
const widgetForm = ref({
  title: '',
  data_source: 'processes',
  chart_type: 'bar',
  dimension_field: 'status',
  metric_field: 'id',
  aggregation_type: 'COUNT',
  color_palette: 'indigo',
  width_cols: 6,
  dashboard_id: props.activeDashboard?.id || null,
});

const dashboardForm = ref({
  title: '',
  description: '',
  is_default: false,
});

const scheduleForm = ref({
  title: '',
  dashboard_id: props.activeDashboard?.id || null,
  frequency_type: 'instant',
  dispatch_time: '08:30',
  recipients: '',
  include_pdf: true,
  include_excel: true,
});

const previewChartData = ref(null);

const chartTypes = [
  { id: 'bar', label: 'Kolon / Bar', icon: '📊' },
  { id: 'line', label: 'Çizgi / Line', icon: '📈' },
  { id: 'area', label: 'Alan / Area', icon: '📉' },
  { id: 'donut', label: 'Donut', icon: '🍩' },
  { id: 'pie', label: 'Pasta / Pie', icon: '🥧' },
  { id: 'kpi_card', label: 'KPI Kartı', icon: '🎯' },
];

const palettes = [
  { id: 'indigo', label: 'Indigo', color: '#6366F1' },
  { id: 'emerald', label: 'Zümrüt', color: '#10B981' },
  { id: 'blue', label: 'Mavi', color: '#3B82F6' },
  { id: 'amber', label: 'Amber', color: '#F59E0B' },
  { id: 'rose', label: 'Gül / Kırmızı', color: '#F43F5E' },
];

// Veri Kaynaklarını Çekirdek vs Dinamik Form Olarak Ayrıştır
const coreKeys = ['processes', 'tasks', 'termin_sla', 'follow_ups'];

const coreDataSources = computed(() => {
  const result = {};
  for (const k of coreKeys) {
    if (props.dataSourcesMeta[k]) {
      result[k] = props.dataSourcesMeta[k];
    }
  }
  return result;
});

const dynamicFormDataSources = computed(() => {
  const result = {};
  for (const [k, v] of Object.entries(props.dataSourcesMeta)) {
    if (!coreKeys.includes(k)) {
      result[k] = v;
    }
  }
  return result;
});

const dynamicFormDataSourcesCount = computed(() => {
  return Object.keys(dynamicFormDataSources.value).length;
});

const availableDimensions = computed(() => {
  const ds = props.dataSourcesMeta[widgetForm.value.data_source];
  return ds?.dimensions || [];
});

const availableMetrics = computed(() => {
  const ds = props.dataSourcesMeta[widgetForm.value.data_source];
  return ds?.metrics || [];
});

const applyGlobalFilters = () => {
  router.get(route('reports.index'), {
    dashboard_id: props.activeDashboard?.id,
    date_range: selectedDateRange.value,
    start_date: startDate.value,
    end_date: endDate.value,
    department_id: selectedDepartmentId.value || undefined,
    workflow_id: selectedWorkflowId.value || undefined,
  }, { preserveState: true });
};

const switchDashboard = (dashId) => {
  router.get(route('reports.index'), {
    dashboard_id: dashId,
    date_range: selectedDateRange.value,
    start_date: startDate.value,
    end_date: endDate.value,
    department_id: selectedDepartmentId.value || undefined,
    workflow_id: selectedWorkflowId.value || undefined,
  }, { preserveState: true });
};

// Takvimden doğrudan tarih seçildiğinde
const applyCalendarDateRange = () => {
  if (startDate.value && endDate.value) {
    selectedDateRange.value = 'custom';
  }
  router.get(route('reports.index'), {
    dashboard_id: props.activeDashboard?.id,
    date_range: selectedDateRange.value,
    start_date: startDate.value,
    end_date: endDate.value,
    department_id: selectedDepartmentId.value || undefined,
    workflow_id: selectedWorkflowId.value || undefined,
  }, { preserveState: true });
};

// Preset dropdown seçildiğinde
const applyPresetRange = () => {
  startDate.value = '';
  endDate.value = '';
  router.get(route('reports.index'), {
    dashboard_id: props.activeDashboard?.id,
    date_range: selectedDateRange.value,
    start_date: '',
    end_date: '',
    department_id: selectedDepartmentId.value || undefined,
    workflow_id: selectedWorkflowId.value || undefined,
  }, { preserveState: true });
};

// Filtreleri tamamen temizle
const clearAllFilters = () => {
  selectedDateRange.value = 'all_time';
  startDate.value = '';
  endDate.value = '';
  selectedDepartmentId.value = '';
  selectedWorkflowId.value = '';
  router.get(route('reports.index'), {
    dashboard_id: props.activeDashboard?.id,
    date_range: 'all_time',
    start_date: '',
    end_date: '',
    department_id: undefined,
    workflow_id: undefined,
  }, { preserveState: true });
};

const selectDataSource = (dsKey) => {
  widgetForm.value.data_source = dsKey;
  const ds = props.dataSourcesMeta[dsKey];
  if (ds?.dimensions?.length) {
    widgetForm.value.dimension_field = ds.dimensions[0].field;
  }
  if (ds?.metrics?.length) {
    widgetForm.value.metric_field = ds.metrics[0].field;
  }
  triggerPreview();
};

const openNewWidgetModal = () => {
  widgetForm.value.dashboard_id = props.activeDashboard?.id;
  widgetForm.value.title = '';
  selectDataSource('processes');
  showWidgetModal.value = true;
  triggerPreview();
};

// Canlı Önizleme Tetikleyici
let previewTimeout = null;
const triggerPreview = () => {
  clearTimeout(previewTimeout);
  previewTimeout = setTimeout(async () => {
    isPreviewLoading.value = true;
    try {
      const resp = await axios.post(route('reports.preview'), {
        ...widgetForm.value,
        date_range: selectedDateRange.value,
        start_date: startDate.value,
        end_date: endDate.value,
      });
      previewChartData.value = resp.data;
    } catch (err) {
      console.error('Önizleme hatası:', err);
    } finally {
      isPreviewLoading.value = false;
    }
  }, 250);
};

const saveWidget = () => {
  isSubmitting.value = true;
  router.post(route('reports.widgets.store'), {
    ...widgetForm.value,
    dashboard_id: props.activeDashboard?.id,
  }, {
    onSuccess: () => {
      showWidgetModal.value = false;
      isSubmitting.value = false;
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const deleteWidget = (widget) => {
  if (confirm(`"${widget.title}" grafiğini bu panodan kaldırmak istediğinize emin misiniz?`)) {
    router.delete(route('reports.widgets.destroy', { widget: widget.id }), {
      data: { dashboard_id: props.activeDashboard?.id },
    });
  }
};

const handleTogglePin = (widget) => {
  router.post(route('reports.widgets.toggle-pin', { widget: widget.id }), {}, {
    preserveScroll: true,
  });
};

const saveDashboard = () => {
  router.post(route('reports.dashboards.store'), dashboardForm.value, {
    onSuccess: () => {
      showNewDashboardModal.value = false;
      dashboardForm.value = { title: '', description: '', is_default: false };
    },
  });
};

const openEditDashboardModal = () => {
  if (!props.activeDashboard) return;
  editDashboardForm.value = {
    title: props.activeDashboard.title || '',
    description: props.activeDashboard.description || '',
    is_default: !!props.activeDashboard.is_default,
  };
  showEditDashboardModal.value = true;
};

const saveEditDashboard = () => {
  if (!props.activeDashboard) return;
  isUpdatingDashboard.value = true;
  router.put(route('reports.dashboards.update', { dashboard: props.activeDashboard.id }), editDashboardForm.value, {
    onSuccess: () => {
      showEditDashboardModal.value = false;
      isUpdatingDashboard.value = false;
    },
    onError: () => {
      isUpdatingDashboard.value = false;
    },
  });
};

const openDeleteDashboardModal = () => {
  showDeleteDashboardModal.value = true;
};

const confirmDeleteDashboard = () => {
  if (!props.activeDashboard) return;
  isDeletingDashboard.value = true;
  router.delete(route('reports.dashboards.destroy', { dashboard: props.activeDashboard.id }), {
    onSuccess: () => {
      showDeleteDashboardModal.value = false;
      isDeletingDashboard.value = false;
    },
    onError: () => {
      isDeletingDashboard.value = false;
    },
  });
};

const saveSchedule = () => {
  isSubmittingSchedule.value = true;
  scheduleForm.value.dashboard_id = props.activeDashboard?.id;
  router.post(route('reports.schedules.store'), scheduleForm.value, {
    onSuccess: () => {
      isSubmittingSchedule.value = false;
      showScheduleModal.value = false;
      scheduleForm.value.title = '';
      scheduleForm.value.recipients = '';
    },
    onError: () => {
      isSubmittingSchedule.value = false;
    },
  });
};

const sendScheduleNow = (sc) => {
  sendingScheduleId.value = sc.id;
  router.post(route('reports.schedules.send-now', { schedule: sc.id }), {}, {
    onFinish: () => {
      sendingScheduleId.value = null;
    },
  });
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('tr-TR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  });
};
</script>
