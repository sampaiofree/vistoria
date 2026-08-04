<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    caption: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'default',
    },
});

const toneClasses = computed(() => ({
    critical: 'border-rose-200 bg-rose-50 text-rose-800',
    teal: 'border-teal-200 bg-teal-50 text-teal-800',
    navy: 'border-slate-800 bg-slate-950 text-white',
    default: 'border-slate-200 bg-white text-slate-950',
}[props.tone] ?? 'border-slate-200 bg-white text-slate-950'));

const captionClasses = computed(() => (
    props.tone === 'navy' ? 'text-slate-300' : 'text-slate-500'
));
</script>

<template>
    <article class="min-w-0 rounded-2xl border p-4 shadow-sm" :class="toneClasses">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] opacity-70">{{ label }}</p>
        <p class="mt-2 truncate text-2xl font-semibold tracking-tight">{{ value }}</p>
        <p v-if="caption" class="mt-1 text-xs leading-5" :class="captionClasses">{{ caption }}</p>
    </article>
</template>
