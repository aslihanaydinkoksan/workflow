<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    treeTypeId: { type: Number, required: true }
});

const emit = defineEmits(['add', 'edit', 'remove', 'dragstart', 'dragover', 'drop']);

const isOpen = ref(true);
const showDetails = ref(false);

const toggle = () => {
    if (props.item.children && props.item.children.length > 0) isOpen.value = !isOpen.value;
};

// AKILLI İSİMLENDİRME: Metadata içinde isim barındıran kolonları önceliklendir
const displayName = computed(() => {
    const meta = props.item.node.metadata || {};
    return meta.personel_adi || meta.name || meta.title || props.item.node.label;
});

const formatValue = (value) => {
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'boolean') return value ? 'Evet' : 'Hayır';
    return value || '-';
};
</script>

<template>
    <div class="pl-6 relative mt-3">
        <!-- Dikey Ağaç Çizgisi -->
        <div class="absolute top-0 left-3 bottom-0 w-px bg-indigo-200"></div>
        
        <div class="relative mb-3">
            <!-- Yatay Ağaç Çizgisi -->
            <div class="absolute top-6 -left-3 w-3 h-px bg-indigo-200"></div>
            
            <!-- Düğüm Kartı -->
            <div class="bg-white rounded-xl p-3 border shadow-sm relative group transition-all"
                 :class="showDetails ? 'border-indigo-300 ring-1 ring-indigo-100 shadow-md' : 'border-gray-200 hover:border-indigo-300 hover:shadow-md'"
                 @dragover.prevent="$emit('dragover', $event)" 
                 @drop="$emit('drop', $event, item.node.id)">
                
                <div class="flex items-center justify-between">
                    
                    <div class="flex items-center gap-3 cursor-move" draggable="true" @dragstart="$emit('dragstart', item.node.id)">
                        <!-- Genişletme Butonu -->
                        <button v-if="item.children && item.children.length" @click="toggle" 
                                class="w-6 h-6 flex items-center justify-center rounded-full bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition">
                            <svg v-if="isOpen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            <svg v-else class="w-4 h-4 -rotate-90 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div v-else class="w-6 h-6 flex items-center justify-center"><div class="w-1.5 h-1.5 rounded-full bg-gray-300"></div></div>

                        <div>
                            <!-- Akıllı İsimlendirme Kullanıldı -->
                            <span class="font-bold text-gray-800 text-sm">{{ displayName }}</span>
                            <span v-if="item.node.node_subtype" class="ml-2 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-gray-100 text-gray-500">
                                {{ item.node.node_subtype }}
                            </span>
                        </div>
                    </div>

                    <!-- Aksiyon Butonları -->
                    <div class="opacity-0 group-hover:opacity-100 flex gap-1 transition-opacity">
                        <button @click="showDetails = !showDetails" title="Detayları Gör" class="w-7 h-7 flex items-center justify-center rounded bg-purple-50 text-purple-600 hover:bg-purple-100 font-bold">ℹ️</button>
                        <button @click="$emit('add', item.node.id)" title="Alt Düğüm Ekle" class="w-7 h-7 flex items-center justify-center rounded bg-green-50 text-green-600 hover:bg-green-100 font-bold">+</button>
                        <button @click="$emit('edit', item.node)" title="Düzenle" class="w-7 h-7 flex items-center justify-center rounded bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold">✎</button>
                        <button @click="$emit('remove', item.node.id)" title="Sil" class="w-7 h-7 flex items-center justify-center rounded bg-red-50 text-red-600 hover:bg-red-100 font-bold">-</button>
                    </div>
                </div>

                <!-- Detay (Metadata) Paneli -->
                <div v-show="showDetails" class="mt-3 pt-3 border-t border-gray-100 animate-fade-in-down">
                    <div v-if="item.node.metadata && Object.keys(item.node.metadata).length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div v-for="(value, key) in item.node.metadata" :key="key" class="bg-gray-50 px-2 py-1.5 rounded border border-gray-100 text-[11px]">
                            <span class="font-bold text-gray-400 block capitalize">{{ key.replace(/_/g, ' ') }}</span>
                            <span class="text-gray-800 font-medium break-words">{{ formatValue(value) }}</span>
                        </div>
                    </div>
                    <div v-else class="text-[11px] text-gray-400 italic text-center py-1">Detay bulunmuyor.</div>
                </div>
            </div>

            <!-- Rekürsif Çağrı (Çocuklar) -->
            <div v-show="isOpen" v-if="item.children && item.children.length">
                <HierarchyNode 
                    v-for="child in item.children" 
                    :key="child.node.id" 
                    :item="child" 
                    :treeTypeId="treeTypeId"
                    @add="$emit('add', $event)" 
                    @edit="$emit('edit', $event)" 
                    @remove="$emit('remove', $event)"
                    @dragstart="$emit('dragstart', $event)" 
                    @dragover="$emit('dragover', $event)" 
                    @drop="$emit('drop', $event, arguments[1])"
                />
            </div>
        </div>
    </div>
</template>