<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ağaç Tipi ve Şema Tasarımcısı</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(-45deg, #1e1e2f, #2d2b42, #1a233a, #231a30);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            color: #fff;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }
    </style>
</head>

<body class="p-8 font-sans antialiased">

    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-light tracking-wide flex items-center gap-3">
                <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                Şema Tasarımcısı (Schema Builder)
            </h2>
            <button onclick="resetForm()"
                class="glass-panel px-5 py-2.5 rounded-xl hover:bg-white/10 transition text-sm font-medium">
                + Yeni Ağaç Tipi Tasarla
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <div class="lg:col-span-4 flex flex-col gap-4 max-h-[80vh] overflow-y-auto custom-scrollbar pr-2">
                @forelse($treeTypes as $type)
                    <div onclick="editTreeType({{ $type->id }}, '{{ addslashes($type->key) }}', '{{ addslashes($type->display_name) }}', '{{ addslashes($type->description) }}', {{ json_encode($type->schema ?? []) }})"
                        class="glass-panel p-5 rounded-2xl cursor-pointer hover:bg-white/10 transition group relative border-l-4 {{ $type->is_active ? 'border-l-green-500' : 'border-l-red-500' }}">
                        <h4 class="text-lg font-semibold tracking-wide">{{ $type->display_name }}</h4>
                        <p class="text-xs text-white/50 mt-1 uppercase tracking-wider font-mono">{{ $type->key }}</p>
                        <button onclick="deleteTreeType(event, {{ $type->id }})"
                            class="absolute top-4 right-4 text-red-400 opacity-0 group-hover:opacity-100 hover:text-red-300 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                        </button>
                    </div>
                @empty
                    <div class="glass-panel p-6 rounded-2xl text-center text-white/60 text-sm">Tanımlı ağaç tipi yok.
                    </div>
                @endforelse
            </div>

            <div class="lg:col-span-8 glass-panel rounded-3xl p-8 relative">
                <form id="treeTypeForm" onsubmit="handleFormSubmit(event)">
                    <input type="hidden" id="typeId" value="">

                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div>
                            <label
                                class="block text-xs font-semibold text-white/70 mb-2 uppercase tracking-wider">Görünen
                                Ad <span class="text-red-400">*</span></label>
                            <input type="text" id="displayName" required
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-white/30 transition">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-white/70 mb-2 uppercase tracking-wider">Sistem
                                Anahtarı (Key)</label>
                            <input type="text" id="typeKey" placeholder="Boş bırakırsanız otomatik üretilir"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-white/30 transition font-mono text-sm">
                        </div>
                    </div>

                    <div class="mb-8">
                        <label
                            class="block text-xs font-semibold text-white/70 mb-2 uppercase tracking-wider">Açıklama</label>
                        <textarea id="typeDescription" rows="2"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-white/30 transition custom-scrollbar"></textarea>
                    </div>

                    <div class="border-t border-white/10 pt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-light tracking-wide text-white/90">Dinamik Şema (Metadata Fields)
                            </h3>
                            <button type="button" onclick="addSchemaRow()"
                                class="text-xs bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition flex items-center gap-1 font-medium text-green-300">
                                + Yeni Özellik (Alan) Ekle
                            </button>
                        </div>

                        <div id="schemaRowsContainer" class="space-y-3">
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 mt-10 pt-6 border-t border-white/10">
                        <button type="submit" id="saveBtn"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-blue-500/70 to-indigo-600/70 hover:from-blue-500 hover:to-indigo-600 text-white font-medium transition duration-200 shadow-lg">
                            Yeni Kayıt Oluştur
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const apiBaseUrl = '/admin/tree-types';
        const schemaContainer = document.getElementById('schemaRowsContainer');
        const SCHEMA_FEATURES = {
            text: {
                requiresUnit: false,
                requiresOptions: false,
                label: "Kısa Metin"
            },
            textarea: {
                requiresUnit: false,
                requiresOptions: false,
                label: "Uzun Metin"
            },
            number: {
                requiresUnit: true,
                requiresOptions: false,
                label: "Sayısal Değer"
            },
            boolean: {
                requiresUnit: false,
                requiresOptions: false,
                label: "Evet/Hayır"
            },
            date: {
                requiresUnit: false,
                requiresOptions: false,
                label: "Tarih"
            },
            select: {
                requiresUnit: false,
                requiresOptions: true,
                label: "Açılır Liste (Tekli)"
            },
            multiselect: {
                requiresUnit: false,
                requiresOptions: true,
                label: "Çoklu Seçim"
            }
        };

        // Form Sıfırlama (Yeni Kayıt Modu)
        function resetForm() {
            document.getElementById('typeId').value = '';
            document.getElementById('displayName').value = '';
            document.getElementById('typeKey').value = '';
            document.getElementById('typeDescription').value = '';
            document.getElementById('saveBtn').innerText = 'Yeni Kayıt Oluştur';
            schemaContainer.innerHTML = '';
        }

        // Düzenleme Modu (Sol panelden karta tıklandığında)
        function editTreeType(id, key, name, desc, schema) {
            document.getElementById('typeId').value = id;
            document.getElementById('typeKey').value = key;
            document.getElementById('displayName').value = name;
            document.getElementById('typeDescription').value = desc;
            document.getElementById('saveBtn').innerText = 'Değişiklikleri Güncelle';

            schemaContainer.innerHTML = '';
            if (Array.isArray(schema)) {
                schema.forEach(col => addSchemaRow(col.field, col.type, col.required, col.unit, col.options));
            }
        }
        // Seçenekler array'ini virgüllü stringe çeviren yardımcı fonksiyon
        const arrayToString = (arr) => Array.isArray(arr) ? arr.join(', ') : (arr || '');
        // Dinamik Satır Ekleme
        function addSchemaRow(field = '', type = 'text', isRequired = false, unit = '', options = []) {
            const row = document.createElement('div');
            row.className =
                'schema-row flex flex-col gap-3 bg-white/5 p-4 rounded-xl border border-white/10 transition-all hover:bg-white/10';

            // Konfigürasyondan select option'larını dinamik üret
            const typeOptionsHtml = Object.entries(SCHEMA_FEATURES).map(([key, feature]) =>
                `<option value="${key}" ${type === key ? 'selected' : ''}>${feature.label}</option>`
            ).join('');

            row.innerHTML = `
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <input type="text" placeholder="Alan Adı (örn: stok_miktari)" value="${field}" required class="schema-field w-full bg-transparent border-b border-white/20 px-2 py-1 text-white text-sm focus:outline-none focus:border-white/50 font-mono placeholder-white/30">
                    </div>
                    <div class="w-48">
                        <select onchange="updateRowUI(this)" class="schema-type w-full bg-black/30 border border-white/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none [&>option]:text-black">
                            ${typeOptionsHtml}
                        </select>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-white/70 w-24">
                        <input type="checkbox" class="schema-required w-4 h-4 rounded accent-blue-500" ${isRequired ? 'checked' : ''}>
                        <label>Zorunlu</label>
                    </div>
                    <button type="button" onclick="this.closest('.schema-row').remove()" class="text-red-400 hover:text-red-300 p-2 transition" title="Satırı Sil">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Ekstra Alanlar (JS ile dinamik açılıp kapanacak) -->
                <div class="extra-fields-container flex gap-4 pl-2 mt-1 border-l-2 border-white/20 ml-2 hidden">
                    <div class="unit-wrapper flex-none w-32 hidden">
                        <input type="text" placeholder="Birim (Örn: kg)" value="${unit}" class="schema-unit w-full bg-black/20 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-blue-400/50 placeholder-white/30">
                    </div>
                    <div class="options-wrapper flex-1 hidden">
                        <input type="text" placeholder="Seçenekleri virgülle ayırarak yazın (Örn: Kırmızı, Mavi, Yeşil)" value="${arrayToString(options)}" class="schema-options w-full bg-black/20 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-blue-400/50 placeholder-white/30">
                    </div>
                </div>
            `;

            schemaContainer.appendChild(row);

            // Satır eklendiğinde UI'ı mevcut tipe göre güncelle
            updateRowUI(row.querySelector('.schema-type'));
        }
        /*
         * UI Güncelleme: (İF/ELSE KULLANILMAMIŞTIR)
         * Elementin tipini alıp konfigürasyon objesindeki boolean değerlere göre
         * CSS sınıflarını (hidden) toggle (aç/kapat) yapar.
         */
        function updateRowUI(selectElement) {
            const row = selectElement.closest('.schema-row');
            const type = selectElement.value;
            const features = SCHEMA_FEATURES[type] || {
                requiresUnit: false,
                requiresOptions: false
            };

            const extraContainer = row.querySelector('.extra-fields-container');
            const unitWrapper = row.querySelector('.unit-wrapper');
            const optionsWrapper = row.querySelector('.options-wrapper');

            // ClassList toggle boolean parametresi alır (true ise ekler, false ise çıkarır)
            unitWrapper.classList.toggle('hidden', !features.requiresUnit);
            optionsWrapper.classList.toggle('hidden', !features.requiresOptions);

            // Konteynerın gösterilip gösterilmeyeceği (en az bir özellik varsa göster)
            extraContainer.classList.toggle('hidden', !(features.requiresUnit || features.requiresOptions));
        }
        /*
         * JSON OLUŞTURMA VE GÖNDERME MANTIĞI:
         * 1. '.schema-row' class'ına sahip tüm DOM elemanları taranır.
         * 2. Her satırın içindeki input/select değerleri okunarak bir JS Objesine çevrilir.
         * 3. Bu objeler array'e (schemaArray) basılır ve fetch payload'u olarak sunucuya yollanır.
         */
        async function handleFormSubmit(e) {
            e.preventDefault();

            const id = document.getElementById('typeId').value;
            const payload = {
                display_name: document.getElementById('displayName').value,
                key: document.getElementById('typeKey').value,
                description: document.getElementById('typeDescription').value,
                schema: []
            };

            document.querySelectorAll('.schema-row').forEach(row => {
                const fieldInput = row.querySelector('.schema-field').value.trim();
                if (!fieldInput) return; // Boş satırı atla (Guard Clause)

                const type = row.querySelector('.schema-type').value;
                const features = SCHEMA_FEATURES[type];

                // Temel Şema Objesi
                const schemaItem = {
                    field: fieldInput.toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                    type: type,
                    required: row.querySelector('.schema-required').checked
                };

                // Eğer aktif tip 'unit' destekliyorsa objeye ekle (if/else ağacı yerine attribute mapping)
                features.requiresUnit && (
                    schemaItem.unit = row.querySelector('.schema-unit').value.trim()
                );

                // Eğer aktif tip 'options' destekliyorsa objeye dizi olarak ekle
                features.requiresOptions && (
                    schemaItem.options = row.querySelector('.schema-options').value
                    .split(',')
                    .map(s => s.trim())
                    .filter(s => s.length > 0)
                );

                payload.schema.push(schemaItem);
            });

            try {
                const url = id ? `${apiBaseUrl}/${id}` : apiBaseUrl;
                const res = await fetch(url, {
                    method: id ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const err = await res.json();
                    let errorMsg = err.message || "Girdiğiniz verileri kontrol edin.";

                    // Laravel'in gönderdiği spesifik 422 hatalarını ekrana bas
                    if (err.errors) {
                        errorMsg += "\n\nDetaylar:\n";
                        for (let field in err.errors) {
                            errorMsg += `- ${err.errors[field].join(', ')}\n`;
                        }
                    }
                    alert("İşlem başarısız:\n" + errorMsg);
                }
            } catch (error) {
                alert("Sunucuya ulaşılamadı.");
            }
        }

        // Ağaç Tipi Silme İşlemi
        async function deleteTreeType(e, id) {
            e.stopPropagation(); // Kartın click eventini tetiklememesi için
            if (!confirm("Bu ağaç tipini silmek istediğinize emin misiniz? (Buna bağlı düğümler de etkilenebilir)"))
                return;

            try {
                const res = await fetch(`${apiBaseUrl}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                if (res.ok) location.reload();
            } catch (err) {
                alert("Hata oluştu.");
            }
        }
    </script>
</body>

</html>
