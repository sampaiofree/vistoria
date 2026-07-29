<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: String, required: true } });

const statuses = {
    planned: ['Planejada', 'bg-slate-100 text-slate-700'],
    in_progress: ['Em inspeção', 'bg-blue-100 text-blue-800'],
    awaiting_review: ['Aguardando revisão', 'bg-amber-100 text-amber-800'],
    in_correction: ['Em correção', 'bg-orange-100 text-orange-800'],
    awaiting_approval: ['Aguardando aprovação', 'bg-violet-100 text-violet-800'],
    approved: ['Aprovada', 'bg-indigo-100 text-indigo-800'],
    report_generated: ['Relatório gerado', 'bg-cyan-100 text-cyan-800'],
    released: ['Liberada', 'bg-emerald-100 text-emerald-800'],
    canceled: ['Cancelada', 'bg-rose-100 text-rose-800'],
};

const definition = computed(() => statuses[props.status] ?? [props.status, 'bg-slate-100 text-slate-700']);
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="definition[1]">
        {{ definition[0] }}
    </span>
</template>
