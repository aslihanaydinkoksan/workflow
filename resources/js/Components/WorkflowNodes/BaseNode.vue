<script setup>
import { Handle, Position } from '@vue-flow/core';
import { NodeToolbar } from '@vue-flow/node-toolbar';
import { computed, inject, ref } from 'vue';

const props = defineProps({
    id: String,
    data: Object,
    color: String,
    icon: String,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

// forms listesini inject ile alıyoruz (Designer'dan provide edilecek)
const formsList = inject('formsList', ref([]));

const getSubtitleInfo = (data) => {
    if (data.taskType === 'start') return { icon: '▶', text: 'Süreci Başlatır' };
    if (data.taskType === 'end') return { icon: '■', text: 'Süreci Sonlandırır' };
    
    if (data.taskType === 'form') {
        // Form görevi: bağlı form adını göster
        if (data.subFormId) {
            const foundForm = formsList.value?.find(f => f.id == data.subFormId);
            return { icon: '📋', text: foundForm ? foundForm.name : 'Form Seçili' };
        }
        return { icon: '📋', text: 'Form Seçilmedi' };
    }
    
    if (data.taskType === 'approval' || data.taskType === 'review') {
        let icon = '👤';
        let text = 'Atanmamış';

        if (data.assignType === 'hierarchy') {
            text = data.assignValue === 'manager_1' ? '1. Amir' : '2. Amir';
        } else if (data.assignType === 'department') {
            icon = '🏢';
            text = 'Departman Yöneticisi';
        } else if (data.assignType === 'directorate') {
            icon = '🏛';
            text = 'Direktörlük Yöneticisi';
        } else if (data.assignType === 'user') {
            text = 'Belirli Kullanıcı';
        } else if (data.assignType === 'role') {
            icon = '🔑';
            text = 'Belirli Rol';
        } else if (data.assignType) {
            text = 'Atanmış';
        }

        if (data.rejectEnabled) {
            text += ' · ↩ Red';
        }

        return { icon, text };
    }
    
    if (data.taskType === 'notify') {
        return { icon: '✉', text: data.notifyEmail || 'E-posta Ayarlanmadı' };
    }
    
    if (data.taskType === 'document') {
        return { icon: '📄', text: 'Belge Çıktısı' };
    }
    
    if (data.taskType === 'storage') {
        return { icon: '⏳', text: 'Bekleme Noktası' };
    }
    
    return { icon: 'ℹ', text: 'Yapılandırılmamış' };
};

const subtitle = computed(() => getSubtitleInfo(props.data));
</script>

<template>
    <div class="relative group w-64 bg-white rounded-xl shadow-md border border-gray-200 transition-all duration-200 hover:shadow-lg hover:border-gray-300">
        
        <!-- Node Toolbar -->
        <NodeToolbar :is-visible="data.selected" position="right" class="flex gap-2 p-1.5 bg-gray-900 rounded-lg shadow-xl translate-x-2 -translate-y-2">
            <button @click="emit('openSettings')" class="p-2 text-white hover:bg-gray-700 rounded-md transition-colors" title="Ayarlar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </button>
            <button @click="emit('duplicate')" class="p-2 text-white hover:bg-gray-700 rounded-md transition-colors" title="Çoğalt">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            </button>
            <button @click="emit('remove')" class="p-2 text-red-400 hover:bg-gray-700 hover:text-red-300 rounded-md transition-colors" title="Sil">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
        </NodeToolbar>

        <!-- Input Handle -->
        <Handle type="target" :position="Position.Top" class="w-3 h-3 bg-gray-400 border-2 border-white" />

        <div class="flex overflow-hidden rounded-xl">
            <!-- Left Color Bar & Icon -->
            <div class="w-12 flex flex-col items-center justify-center py-4 text-white shrink-0" :style="{ backgroundColor: color }">
                <div v-html="icon" class="w-6 h-6"></div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 p-3 bg-white">
                <div class="text-xs font-bold text-gray-800 mb-0.5 truncate pr-2">{{ data.label }}</div>
                <div class="flex items-center text-[10px] text-gray-500 mt-1">
                    <span class="mr-1.5 shrink-0">{{ subtitle.icon }}</span>
                    <span class="truncate">{{ subtitle.text }}</span>
                </div>
            </div>
        </div>

        <!-- Output Handles (Slot for custom handles, fallback to default) -->
        <slot name="source-handles">
            <Handle type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white" :style="{ backgroundColor: color }" />
        </slot>
    </div>
</template>
