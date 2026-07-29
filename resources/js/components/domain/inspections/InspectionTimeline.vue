<script setup>
import InspectionStatusBadge from './InspectionStatusBadge.vue';
defineProps({ history: { type: Array, default: () => [] } });
</script>
<template>
    <ol class="space-y-4 border-l-2 border-slate-200 pl-5">
        <li v-for="item in history" :key="item.id" class="relative">
            <span class="absolute -left-[1.65rem] top-1.5 h-3 w-3 rounded-full bg-teal-600 ring-4 ring-white"></span>
            <div class="flex flex-wrap items-center gap-2"><InspectionStatusBadge :status="item.to_status" /><span class="text-xs text-slate-500">{{ item.created_at }}</span></div>
            <p class="mt-1 text-sm text-slate-600">Por {{ item.user?.name || 'Sistema' }}</p>
            <p v-if="item.justification" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><strong>Justificativa:</strong> {{ item.justification }}</p>
        </li>
    </ol>
</template>
