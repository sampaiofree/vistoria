<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const meta = computed(() => {
    switch (props.status) {
        case 'draft':
            return {
                label: 'Rascunho',
                className: 'border-amber-200 bg-amber-50 text-amber-700',
            };
        case 'complete':
            return {
                label: 'Publicada',
                className: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            };
        case 'not_assessed':
            return {
                label: 'Pendente',
                className: 'border-slate-200 bg-slate-100 text-slate-700',
            };
        default:
            return {
                label: props.status,
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
