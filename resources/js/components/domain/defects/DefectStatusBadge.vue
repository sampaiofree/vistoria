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
        case 'active':
            return {
                label: 'Ativa',
                className: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            };
        case 'repaired':
            return {
                label: 'Reparada',
                className: 'border-sky-200 bg-sky-50 text-sky-700',
            };
        case 'archived':
            return {
                label: 'Arquivada',
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
        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide"
        :class="meta.className"
    >
        {{ meta.label }}
    </span>
</template>
