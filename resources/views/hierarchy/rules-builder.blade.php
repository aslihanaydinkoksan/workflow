<style>
    .glass-rule-panel {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }

    .rule-input {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
    }

    .rule-input:focus {
        border-color: rgba(255, 255, 255, 0.5);
        outline: none;
    }
</style>

<div id="ruleBuilderModal"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all duration-300 opacity-0 pointer-events-none">
    <div class="glass-rule-panel w-full max-w-4xl p-8 rounded-3xl transform transition-transform duration-300 scale-95"
        id="ruleModalContent">

        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-light tracking-wide text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z">
                    </path>
                </svg>
                Görsel Kural Sihirbazı
            </h3>
            <button onclick="closeRuleBuilder()" class="text-white/50 hover:text-white transition"><svg class="w-6 h-6"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg></button>
        </div>

        <form id="ruleForm" onsubmit="saveRule(event)">
            <input type="hidden" id="rbWorkflowId" value="1"> <input type="hidden" id="rbNodeId" value="">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-2 uppercase tracking-wider">Kural Adı
                        <span class="text-red-400">*</span></label>
                    <input type="text" id="rbName" placeholder="Örn: Araç SRC Reddi" required
                        class="w-full rule-input rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-2 uppercase tracking-wider">Öncelik
                        (Priority) <span class="text-red-400">*</span></label>
                    <input type="number" id="rbPriority" value="1" required
                        class="w-full rule-input rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>

            <div class="mb-6 bg-white/5 p-4 rounded-xl border border-white/10">
                <label class="block text-xs font-semibold text-white/70 mb-3 uppercase tracking-wider">Koşul
                    Mantığı</label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-white/90">
                        <input type="radio" name="rbConditionType" value="all" checked
                            class="accent-blue-500 w-4 h-4">
                        Aşağıdaki TÜM koşullar sağlanmalı (AND)
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-white/90">
                        <input type="radio" name="rbConditionType" value="any" class="accent-blue-500 w-4 h-4">
                        Aşağıdaki EN AZ BİR koşul sağlanmalı (OR)
                    </label>
                </div>
            </div>

            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-xs font-semibold text-white/70 uppercase tracking-wider">Kurallar
                        (Conditions)</label>
                    <button type="button" onclick="addRuleConditionRow()"
                        class="text-xs bg-blue-500/30 hover:bg-blue-500/50 text-blue-200 px-3 py-1.5 rounded-lg transition font-medium">
                        + Yeni Koşul Ekle
                    </button>
                </div>
                <div id="rbConditionsContainer" class="space-y-3 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                </div>
            </div>

            <div
                class="mb-8 bg-gradient-to-r from-blue-900/30 to-purple-900/30 p-5 rounded-xl border border-blue-500/20">
                <label class="block text-xs font-semibold text-blue-300 mb-3 uppercase tracking-wider">Koşullar
                    Sağlanırsa Yapılacak İşlem (Action)</label>
                <div class="flex gap-4">
                    <div class="w-1/2">
                        <select id="rbActionType"
                            class="w-full rule-input rounded-xl px-4 py-2.5 text-sm [&>option]:text-black">
                            <option value="route_to">Akışı Başka Düğüme Saptır (Route To)</option>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <select id="rbActionTarget"
                            class="w-full rule-input rounded-xl px-4 py-2.5 text-sm [&>option]:text-black">
                            <option value="safety_reject_node">Güvenlik İhlali (Otomatik Ret)</option>
                            <option value="hr_review_node">İnsan Kaynakları İncelemesi</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-white/10">
                <button type="button" onclick="closeRuleBuilder()"
                    class="px-6 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition text-sm">İptal</button>
                <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-500/70 to-emerald-600/70 hover:from-green-500 hover:to-emerald-600 text-white font-medium transition shadow-lg text-sm">
                    Kuralı Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let availableRuleFields = [];
    const rbModal = document.getElementById('ruleBuilderModal');
    const rbModalContent = document.getElementById('ruleModalContent');
    const rbContainer = document.getElementById('rbConditionsContainer');

    /**
     * Düğüm ayarları açılırken çağrılır, backend'den dinamik alanları çeker.
     */
    async function openRuleBuilder(workflowId, nodeId) {
        document.getElementById('rbWorkflowId').value = workflowId;
        document.getElementById('rbNodeId').value = nodeId;
        rbContainer.innerHTML = '';

        try {
            const res = await fetch(`/admin/rules/fields/${workflowId}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            availableRuleFields = data.fields || [];

            if (availableRuleFields.length > 0) addRuleConditionRow(); // İlk satırı otomatik aç

            rbModal.classList.remove('pointer-events-none', 'opacity-0');
            rbModalContent.classList.remove('scale-95');
        } catch (error) {
            alert("Dinamik alanlar yüklenemedi.");
        }
    }

    function closeRuleBuilder() {
        rbModal.classList.add('opacity-0', 'pointer-events-none');
        rbModalContent.classList.add('scale-95');
    }

    /**
     * Ekrana Sol(Alan), Orta(Operatör), Sağ(Değer) üçlüsünü render eder.
     */
    function addRuleConditionRow() {
        // Gelen dinamik alanları select option stringine dönüştür
        const optionsHtml = availableRuleFields.map(f => `<option value="${f.value}">${f.label}</option>`).join('');

        const row = document.createElement('div');
        row.className = 'rb-condition-row flex items-center gap-3 bg-black/20 p-2.5 rounded-lg border border-white/5';
        row.innerHTML = `
            <div class="flex-[2]">
                <select class="rb-field w-full rule-input rounded-lg px-3 py-1.5 text-xs [&>option]:text-black">
                    <option value="">-- Alan Seçin --</option>
                    ${optionsHtml}
                </select>
            </div>
            <div class="flex-1">
                <select class="rb-operator w-full rule-input rounded-lg px-3 py-1.5 text-xs [&>option]:text-black">
                    <option value="==">Eşittir (==)</option>
                    <option value="!=">Eşit Değildir (!=)</option>
                    <option value=">">Büyüktür (>)</option>
                    <option value="<">Küçüktür (<)</option>
                    <option value="contains">İçerir</option>
                </select>
            </div>
            <div class="flex-[2]">
                <input type="text" placeholder="Karşılaştırılacak Değer" class="rb-value w-full rule-input rounded-lg px-3 py-1.5 text-xs placeholder-white/30">
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-300 px-2 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        `;
        rbContainer.appendChild(row);
    }

    /**
     * Form Submit: Ekranda çizilen arayüz JSON yapısına (Mevcut Table Schema) çevrilir.
     */
    async function saveRule(e) {
        e.preventDefault();

        const workflowId = document.getElementById('rbWorkflowId').value;
        const nodeId = document.getElementById('rbNodeId').value;
        const conditionType = document.querySelector('input[name="rbConditionType"]:checked').value;

        // 1. Satırları Gez ve conditions JSON array'ini hazırla
        const conditions = [];
        document.querySelectorAll('.rb-condition-row').forEach(row => {
            const field = row.querySelector('.rb-field').value;
            const operator = row.querySelector('.rb-operator').value;
            let value = row.querySelector('.rb-value').value;

            if (field && value !== "") {
                // Tip zorlaması (Eğer değer 'true'/'false' yazılmışsa boolean yap vs.)
                if (value.toLowerCase() === 'true') value = true;
                if (value.toLowerCase() === 'false') value = false;
                if (!isNaN(value) && value.trim() !== '') value = Number(value);

                conditions.push({
                    field,
                    operator,
                    value
                });
            }
        });

        // 2. Action Bloğunu JSON objesi yap
        const action = {
            type: document.getElementById('rbActionType').value,
            params: {
                target_node_id: document.getElementById('rbActionTarget').value
            }
        };

        const payload = {
            workflow_id: parseInt(workflowId),
            node_id: nodeId,
            name: document.getElementById('rbName').value,
            priority: parseInt(document.getElementById('rbPriority').value),
            condition_type: conditionType,
            conditions: conditions,
            action: action,
            is_active: true
        };

        try {
            const res = await fetch('/admin/rules', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });
            if (res.ok) {
                closeRuleBuilder();
                alert("Kural başarıyla kaydedildi!");
            } else {
                const err = await res.json();
                alert("Hata: " + (err.message || "Bilinmeyen bir hata oluştu."));
            }
        } catch (error) {
            alert("Sunucuya ulaşılamadı.");
        }
    }
</script>
