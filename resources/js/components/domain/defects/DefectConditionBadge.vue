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
        case 'reinspected':
            return { label: 'Reinspecionada', className: 'border-slate-200 bg-slate-50 text-slate-700' };
        case 'reclassified':
            return { label: 'Reclassificada', className: 'border-violet-200 bg-violet-50 text-violet-700' };
        case 'canceled':
            return { label: 'Cancelada', className: 'border-amber-200 bg-amber-50 text-amber-700' };
        case 'canceled_sr':
            return { label: 'Cancelada S/R', className: 'border-orange-200 bg-orange-50 text-orange-700' };
        case 'treated':
            return { label: 'Tratada', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
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
        class="inline-flex items-center rounded border px-2.5 py-1 text-xs font-medium"
        :class="meta.className"
    >
        {{ meta.label }}
    </span>
</template>
