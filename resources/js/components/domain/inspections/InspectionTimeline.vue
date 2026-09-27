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
            <details v-if="item.metadata?.event === 'classification_updated'" class="mt-2 text-sm text-slate-600">
                <summary class="cursor-pointer">Detalhes da alteração</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-2">Campo</th><th class="p-2">Antes</th><th class="p-2">Depois</th></tr></thead>
                        <tbody><tr v-for="change in item.metadata.changes" :key="change.label" class="border-t border-slate-200">
                            <td class="p-2">{{ change.label }}</td><td class="p-2">{{ change.before || '—' }}</td><td class="p-2">{{ change.after || '—' }}</td>
                        </tr></tbody>
                    </table>
                </div>
            </details>
        </li>
    </ol>
</template>
