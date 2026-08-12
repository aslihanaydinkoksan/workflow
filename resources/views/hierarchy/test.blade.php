<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hiyerarşi Yönetimi (Glassmorphism)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
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
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
        }

        .tree-ul {
            position: relative;
            padding-left: 2rem;
        }

        .tree-ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 1rem;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.3);
        }

        .drag-over {
            border: 2px dashed rgba(255, 255, 255, 0.8) !important;
            background: rgba(255, 255, 255, 0.3) !important;
            transform: scale(1.02);
        }

        .dragging {
            opacity: 0.5;
        }

        .node-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .node-container:hover .node-actions {
            opacity: 1;
        }

        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
    </style>
</head>

<body class="p-8 font-sans antialiased">

    <div class="max-w-4xl mx-auto">
        <div class="glass-panel rounded-2xl p-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-light tracking-wide flex items-center gap-3">
                    <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                    Organizasyon Şeması
                </h2>
                <div class="flex gap-2 items-center">

                    <select onchange="window.location.href='?type_id='+this.value"
                        class="glass-panel px-4 py-2 rounded-lg text-sm bg-transparent focus:outline-none [&>option]:text-black cursor-pointer font-semibold mr-2">
                        @if (isset($treeTypes))
                            @foreach ($treeTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ ($treeType->id ?? 0) == $type->id ? 'selected' : '' }}>
                                    {{ $type->display_name }}
                                </option>
                            @endforeach
                        @endif
                    </select>

                    <a href="{{ route('admin.tree-types.index') }}"
                        class="glass-panel px-4 py-2 rounded-lg hover:bg-blue-500/30 transition text-sm flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Şema Yönetimi
                    </a>
                    <button onclick="openModal('create', null, '', '', treeTypeId)"
                        class="glass-panel px-4 py-2 rounded-lg hover:bg-white/30 transition text-sm">
                        + Kök Ekle
                    </button>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-[repeat(auto-fill,minmax(320px,1fr))] gap-8 items-start"
                id="tree-container">
                @if (empty($nodes))
                    <div class="glass-panel p-4 rounded-xl text-center text-white/80 col-span-full">Henüz gösterilecek
                        hiyerarşi verisi bulunmuyor.</div>
                @else
                    @foreach ($nodes as $rootItem)
                        @php $node = $rootItem['node']; @endphp
                        <div class="glass-panel rounded-3xl p-6 relative flex flex-col gap-5 border-t-4 border-t-white/40 shadow-xl transition-all hover:shadow-2xl"
                            ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)"
                            ondrop="handleDrop(event, {{ $node->id }})">

                            <div class="node-container flex items-center justify-between group" draggable="true"
                                ondragstart="handleDragStart(event, {{ $node->id }})"
                                ondragend="handleDragEnd(event)">

                                <div class="font-bold text-xl tracking-wide flex items-center gap-3">
                                    <div class="bg-white/20 p-2 rounded-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        {{ $node->label }}
                                        <span
                                            class="block text-xs font-normal opacity-60 mt-0.5 uppercase tracking-wider">{{ $node->node_subtype }}</span>
                                    </div>
                                </div>

                                <div class="node-actions flex items-center gap-1">
                                    <button
                                        onclick="openModal('create', {{ $node->id }}, '', '', {{ $node->tree_type_id }})"
                                        class="glass-panel w-9 h-9 flex items-center justify-center rounded-full hover:bg-green-500/30 transition text-lg"
                                        title="Alt Düğüm Ekle">+</button>
                                    <button data-meta="{{ json_encode($node->metadata ?? new \stdClass()) }}"
                                        onclick="openModal('edit', {{ $node->id }}, '{{ addslashes($node->label) }}', '{{ $node->node_subtype }}', {{ $node->tree_type_id }}, this.getAttribute('data-meta'))"
                                        class="glass-panel w-9 h-9 flex items-center justify-center rounded-full hover:bg-blue-500/30 transition text-sm"
                                        title="Düzenle">✎</button>
                                    <button onclick="confirmDeleteNode({{ $node->id }})"
                                        class="glass-panel w-9 h-9 flex items-center justify-center rounded-full hover:bg-red-500/30 transition text-lg"
                                        title="Sil">-</button>
                                </div>
                            </div>

                            <hr class="border-white/10">

                            @if (!empty($rootItem['children']))
                                <div class="overflow-y-auto overflow-x-hidden max-h-[500px] pr-2">
                                    @include('hierarchy.partials.tree', [
                                        'items' => $rootItem['children'],
                                        'isChild' => true,
                                        'treeTypeId' => $node->tree_type_id,
                                    ])
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div id="nodeModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm transition-all duration-300 opacity-0 pointer-events-none">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl shadow-2xl border border-white/20 transform transition-transform duration-300 scale-95 max-h-[90vh] overflow-y-auto custom-scrollbar"
            id="modalContent">
            <h3 id="modalTitle" class="text-2xl font-light mb-5 text-white tracking-wide">Düğüm İşlemi</h3>

            <form id="nodeForm" onsubmit="handleModalSubmit(event)">
                <input type="hidden" id="modalAction" value="">
                <input type="hidden" id="modalNodeId" value="">
                <input type="hidden" id="modalParentId" value="">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-white/80 mb-1.5">Ağaç Tipi <span
                            class="text-red-400">*</span></label>
                    <select id="modalTreeTypeId" required onchange="handleTreeTypeChange()"
                        class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-white/50 transition [&>option]:text-black">
                        @php $activeTreeTypeId = !empty($nodes) ? $nodes[0]['node']->tree_type_id : ($treeType->id ?? 1); @endphp
                        @if (isset($treeTypes))
                            @foreach ($treeTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ $type->id == $activeTreeTypeId ? 'selected' : '' }}>
                                    {{ $type->display_name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-white/80 mb-1.5">Görünen Ad (Label) <span
                            class="text-red-400">*</span></label>
                    <input type="text" id="modalLabel" required
                        class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white/50 transition">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-white/80 mb-1.5">Düğüm Tipi (node_subtype)</label>
                    <input list="subtypeList" id="modalSubtype" placeholder="Listeden seçin veya yeni yazın..."
                        class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white/50 transition">
                    <datalist id="subtypeList">
                        @if (isset($subtypes))
                            @foreach ($subtypes as $st)
                                <option value="{{ $st }}">
                            @endforeach
                        @endif
                    </datalist>
                </div>

                <div id="dynamic-metadata-fields" class="mb-6 border-t border-white/20 pt-4 hidden"></div>

                <div class="flex justify-end gap-3 mt-2">
                    <button type="button" onclick="closeModal()"
                        class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition duration-200">İptal</button>
                    <button type="submit"
                        class="px-5 py-2 rounded-xl bg-gradient-to-r from-blue-500/60 to-purple-500/60 hover:from-blue-500/80 hover:to-purple-500/80 border border-white/20 text-white transition duration-200 shadow-lg">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    {{-- <div id="schemaModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm transition-all duration-300 opacity-0 pointer-events-none">
        <div class="glass-panel w-full max-w-2xl p-6 rounded-2xl shadow-2xl border border-white/20 transform transition-transform duration-300 scale-95"
            id="schemaModalContent">
            <h3 class="text-2xl font-light mb-5 text-white tracking-wide">Dinamik Şema Yönetimi
                ({{ $treeType->display_name ?? '' }})</h3>

            <div id="schemaRows" class="space-y-3 max-h-[60vh] overflow-y-auto custom-scrollbar pr-2 mb-4">
            </div>

            <button type="button" onclick="addSchemaRow()"
                class="mb-6 text-sm text-green-300 hover:text-green-200 font-medium tracking-wide flex items-center gap-1">+
                Yeni Alan Ekle</button>

            <div class="flex justify-end gap-3 mt-2 pt-4 border-t border-white/20">
                <button type="button" onclick="closeSchemaModal()"
                    class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition duration-200">İptal</button>
                <button type="button" onclick="saveSchema()"
                    class="px-5 py-2 rounded-xl bg-gradient-to-r from-blue-500/60 to-purple-500/60 hover:from-blue-500/80 hover:to-purple-500/80 text-white transition duration-200 shadow-lg">Şemayı
                    Kaydet</button>
            </div>
        </div>
    </div> --}}

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const apiBaseUrl = '/admin/hierarchy/nodes';

        const modal = document.getElementById('nodeModal');
        const modalContent = document.getElementById('modalContent');
        const treeTypeId = {{ !empty($nodes) ? $nodes[0]['node']->tree_type_id : $treeType->id ?? 1 }};

        const treeTypeObj = @json($treeType ?? []);
        let currentSchema = treeTypeObj?.schema || [];

        const treeTypesData = @json(isset($treeTypes) ? $treeTypes->keyBy('id') : []);
        let currentEditingMetadata = {};

        // --- 1. ŞEMA YÖNETİMİ MANTIĞI ---
        const schemaModal = document.getElementById('schemaModal');
        const schemaModalContent = document.getElementById('schemaModalContent');
        const schemaRowsContainer = document.getElementById('schemaRows');

        function closeSchemaModal() {
            schemaModal.classList.add('opacity-0', 'pointer-events-none');
            schemaModalContent.classList.add('scale-95');
        }

        function renderSchemaRows() {
            schemaRowsContainer.innerHTML = '';
            currentSchema.forEach((col, index) => addSchemaRow(col.field, col.type, col.required, index));
        }

        function addSchemaRow(field = '', type = 'string', required = false, index = Date.now()) {
            const row = document.createElement('div');
            row.className = 'schema-row flex items-center gap-3 bg-white/5 p-3 rounded-xl border border-white/10';
            row.innerHTML = `
                <div class="flex-1">
                    <input type="text" placeholder="Alan Adı (örn: src_belgesi)" value="${field}" class="schema-field w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none">
                </div>
                <div class="w-32">
                    <select class="schema-type w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none [&>option]:text-black">
                        <option value="string" ${type === 'string' ? 'selected' : ''}>Metin</option>
                        <option value="integer" ${type === 'integer' ? 'selected' : ''}>Sayı</option>
                        <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Evet/Hayır</option>
                        <option value="date" ${type === 'date' ? 'selected' : ''}>Tarih</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 text-sm text-white/80 w-24">
                    <input type="checkbox" class="schema-required w-4 h-4 rounded" ${required ? 'checked' : ''}> Zorunlu
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-300 px-2 font-bold">&times;</button>
            `;
            schemaRowsContainer.appendChild(row);
        }

        function handleTreeTypeChange() {
            const selectedTypeId = document.getElementById('modalTreeTypeId').value;
            const treeType = treeTypesData[selectedTypeId];
            const schema = treeType ? treeType.schema : [];
            renderDynamicFields(schema, currentEditingMetadata);
        }

        // --- GÜNCELLENEN RENDER DYNAMIC FIELDS ---
        function renderDynamicFields(schema, metadata = {}) {
            const container = document.getElementById('dynamic-metadata-fields');

            if (!schema || schema.length === 0) {
                container.classList.add('hidden');
                container.innerHTML = '';
                return;
            }

            container.classList.remove('hidden');
            let html =
                `<h4 class="text-sm font-semibold text-white/90 mb-3 tracking-wide">Ek Bilgiler (Metadata)</h4><div class="space-y-3">`;

            schema.forEach(field => {
                const val = metadata[field.field] !== undefined ? metadata[field.field] : '';
                const required = field.required ? 'required' : '';
                const reqStar = field.required ? '<span class="text-red-400">*</span>' : '';

                html +=
                    `<div><label class="block text-xs font-medium text-white/70 mb-1">${field.field} ${reqStar}</label>`;

                // VERİ TİPİNE GÖRE AKILLI INPUT RENDER EDİLİYOR
                if (field.type === 'select') {
                    html += `<select data-field="${field.field}" data-type="select" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm [&>option]:text-black" ${required}>
                        <option value="">Seçiniz...</option>
                        ${(field.options || []).map(opt => `<option value="${opt}" ${val === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>`;
                } else if (field.type === 'multiselect') {
                    const selectedVals = Array.isArray(val) ? val : [];
                    html +=
                        `<select multiple data-field="${field.field}" data-type="multiselect" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm h-24 [&>option]:text-black" ${required}>
                        ${(field.options || []).map(opt => `<option value="${opt}" ${selectedVals.includes(opt) ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                    <span class="text-[10px] text-white/50 mt-1 block">Çoklu seçim için CTRL/CMD tuşuna basılı tutun.</span>`;
                } else if (field.type === 'number' || field.type === 'integer') {
                    html += `<div class="flex items-center gap-2">
                        <input type="number" step="any" data-field="${field.field}" data-type="number" value="${val}" class="meta-input flex-1 bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm" ${required}>
                        ${field.unit ? `<span class="text-white/50 text-xs bg-white/5 px-2 py-2 rounded-lg border border-white/10">${field.unit}</span>` : ''}
                    </div>`;
                } else if (field.type === 'textarea') {
                    html +=
                        `<textarea data-field="${field.field}" data-type="textarea" rows="2" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm" ${required}>${val}</textarea>`;
                } else if (field.type === 'date') {
                    html +=
                        `<input type="date" data-field="${field.field}" data-type="date" value="${val}" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm [&::-webkit-calendar-picker-indicator]:invert" ${required}>`;
                } else if (field.type === 'boolean') {
                    html += `<select data-field="${field.field}" data-type="boolean" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm [&>option]:text-black" ${required}>
                        <option value="">Seçiniz...</option>
                        <option value="true" ${val === true || val === '1' || val === 'true' ? 'selected' : ''}>Evet</option>
                        <option value="false" ${val === false || val === '0' || val === 'false' ? 'selected' : ''}>Hayır</option>
                    </select>`;
                } else {
                    html +=
                        `<input type="text" data-field="${field.field}" data-type="text" value="${val}" class="meta-input w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm" ${required}>`;
                }

                html += `</div>`;
            });

            html += `</div>`;
            container.innerHTML = html;
        }

        async function saveSchema() {
            const rows = document.querySelectorAll('.schema-row');
            const newSchema = [];

            rows.forEach(row => {
                const field = row.querySelector('.schema-field').value.trim();
                if (field) {
                    newSchema.push({
                        field: field.toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                        type: row.querySelector('.schema-type').value,
                        required: row.querySelector('.schema-required').checked
                    });
                }
            });

            try {
                const res = await fetch(`/admin/hierarchy/tree-types/${treeTypeId}/schema`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        schema: newSchema
                    })
                });
                if (res.ok) location.reload();
            } catch (err) {
                alert("Şema kaydedilemedi.");
            }
        }

        // --- 2. DÜĞÜM (NODE) MODAL & DİNAMİK METADATA YÖNETİMİ ---
        function openModal(action, id = null, label = '', subtype = '', targetTreeTypeId = null, nodeMetadataStr = '{}') {
            document.getElementById('modalAction').value = action;

            try {
                currentEditingMetadata = JSON.parse(nodeMetadataStr);
            } catch (e) {
                currentEditingMetadata = {};
            }

            const treeTypeSelect = document.getElementById('modalTreeTypeId');

            if (action === 'create') {
                document.getElementById('modalTitle').innerText = id ? 'Alt Düğüm Ekle' : 'Kök Düğüm Ekle';
                document.getElementById('modalParentId').value = id || '';
                document.getElementById('modalNodeId').value = '';
                document.getElementById('modalLabel').value = '';
                document.getElementById('modalSubtype').value = '';

                if (targetTreeTypeId) treeTypeSelect.value = targetTreeTypeId;
                treeTypeSelect.disabled = false;
            } else if (action === 'edit') {
                document.getElementById('modalTitle').innerText = 'Düğümü Düzenle';
                document.getElementById('modalNodeId').value = id;
                document.getElementById('modalLabel').value = label;
                document.getElementById('modalSubtype').value = subtype;

                if (targetTreeTypeId) treeTypeSelect.value = targetTreeTypeId;
                treeTypeSelect.disabled = true;
            }

            handleTreeTypeChange();

            modal.classList.remove('pointer-events-none', 'opacity-0');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
            setTimeout(() => document.getElementById('modalLabel').focus(), 100);
        }

        function closeModal() {
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('pointer-events-none'), 300);
        }

        // --- GÜNCELLENEN FORM SUBMIT & VERİ TOPLAMA ---
        async function handleModalSubmit(e) {
            e.preventDefault();

            const action = document.getElementById('modalAction').value;
            const selectedTreeTypeId = document.getElementById('modalTreeTypeId').value;
            const label = document.getElementById('modalLabel').value;
            const subtype = document.getElementById('modalSubtype').value;

            // Render edilen div içindeki '.meta-input' class'ına sahip elemanlardan JSON inşası
            const metaDataObj = {};
            document.querySelectorAll('#dynamic-metadata-fields .meta-input').forEach(input => {
                const fieldName = input.getAttribute('data-field');
                const type = input.getAttribute('data-type');

                if (type === 'multiselect') {
                    // Çoklu seçimi Array olarak topla
                    const selected = Array.from(input.selectedOptions).map(opt => opt.value);
                    if (selected.length > 0) metaDataObj[fieldName] = selected;
                } else if (type === 'number') {
                    // Sayısal değer (Float destekli)
                    if (input.value !== '') metaDataObj[fieldName] = parseFloat(input.value);
                } else if (type === 'boolean') {
                    // True/False mantığına çevir
                    if (input.value !== '') metaDataObj[fieldName] = input.value === 'true';
                } else {
                    // Metin, select, date, textarea
                    if (input.value !== '') metaDataObj[fieldName] = input.value;
                }
            });

            try {
                let res;
                if (action === 'create') {
                    const parentId = document.getElementById('modalParentId').value;
                    res = await fetch(apiBaseUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            tree_type_id: selectedTreeTypeId,
                            parent_id: parentId ? parseInt(parentId) : null,
                            label: label,
                            node_subtype: subtype,
                            metadata: metaDataObj
                        })
                    });
                } else if (action === 'edit') {
                    const nodeId = document.getElementById('modalNodeId').value;
                    res = await fetch(`${apiBaseUrl}/${nodeId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            label: label,
                            node_subtype: subtype,
                            metadata: metaDataObj
                        })
                    });
                }

                if (res.ok) {
                    location.reload();
                } else {
                    // Akıllı Hata Okuyucu
                    const errorData = await res.json();
                    let errorMsg = errorData.message || "Eksik alanları kontrol edin.";

                    if (errorData.errors) {
                        errorMsg += "\n\nDetaylar:\n";
                        for (let field in errorData.errors) {
                            errorMsg += `- ${errorData.errors[field].join(', ')}\n`;
                        }
                    }
                    alert("İşlem başarısız oldu:\n" + errorMsg);
                }
            } catch (err) {
                alert("Sunucu ile iletişim kurulamadı.");
            }
        }

        // --- 3. SİLME KONTROLÜ ---
        async function confirmDeleteNode(nodeId) {
            if (!confirm("Bu düğümü ve altındaki TÜM düğümleri silmek istediğinize emin misiniz?")) return;
            try {
                const res = await fetch(`${apiBaseUrl}/${nodeId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                if (res.ok) location.reload();
            } catch (err) {
                alert("Hata oluştu.");
            }
        }

        // --- 4. SÜRÜKLE BIRAK (Drag & Drop Move) ---
        let draggedNodeId = null;

        function handleDragStart(e, id) {
            draggedNodeId = id;
            e.dataTransfer.effectAllowed = 'move';
            e.target.closest('.node-container').classList.add('dragging');
        }

        function handleDragEnd(e) {
            e.target.closest('.node-container').classList.remove('dragging');
            document.querySelectorAll('.glass-panel').forEach(el => el.classList.remove('drag-over'));
            draggedNodeId = null;
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
            return false;
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        async function handleDrop(e, newParentId) {
            e.preventDefault();
            e.stopPropagation();
            e.currentTarget.classList.remove('drag-over');

            if (draggedNodeId === newParentId || !draggedNodeId) return;

            if (!confirm("Düğümü buraya taşımak istiyor musunuz?")) return;

            try {
                const res = await fetch(`${apiBaseUrl}/${draggedNodeId}/move`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        new_parent_id: newParentId
                    })
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const err = await res.json();
                    alert("Hata: " + (err.message || "Taşıma başarısız."));
                }
            } catch (err) {
                alert("Hata oluştu.");
            }
            draggedNodeId = null;
        }
    </script>
</body>

</html>
