<script setup>
import { computed } from 'vue';

const props = defineProps({
    condition: {
        type: String,
        required: true,
    },
});

const meta = computed(() => {
    switch (props.condition) {
        case 'new':
            return { label: 'Nova', className: 'border-sky-200 bg-sky-50 text-sky-700' };
        case 'unchanged':
            return { label: 'Igual', className: 'border-slate-200 bg-slate-50 text-slate-700' };
        case 'worsened':
            return { label: 'Agravou', className: 'border-rose-200 bg-rose-50 text-rose-700' };
        case 'improved':
            return { label: 'Melhorou', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
        case 'repaired':
            return { label: 'Reparada', className: 'border-teal-200 bg-teal-50 text-teal-700' };
        case 'not_located':
            return { label: 'Não localizada', className: 'border-amber-200 bg-amber-50 text-amber-700' };
        case 'not_inspected':
            return { label: 'Não inspecionada', className: 'border-orange-200 bg-orange-50 text-orange-700' };
        default:
            return {
                label: props.condition,
                className: 'border-slate-200 bg-slate-100 text-slate-700',
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide"
        :class="meta.className"
    >
        {{ meta.label }}
    </span>
</template>
