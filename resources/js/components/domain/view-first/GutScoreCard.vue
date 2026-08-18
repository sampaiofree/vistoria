<script setup>
import { computed } from 'vue';

const props = defineProps({
    gut: {
        type: Object,
        default: null,
    },
    classification: {
        type: Object,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const gravity = computed(() => props.gut?.gravity ?? props.gut?.severity ?? props.gut?.g ?? null);
const urgency = computed(() => props.gut?.urgency ?? props.gut?.u ?? null);
const trend = computed(() => props.gut?.trend ?? props.gut?.tendency ?? props.gut?.t ?? null);
const score = computed(() => props.gut?.score ?? props.gut?.gut_score ?? null);
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-950 text-white" :class="compact ? 'p-4' : 'p-5'">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Classificação GUT</p>
                <p v-if="score !== null" class="mt-2 text-3xl font-semibold tracking-tight">{{ score }}</p>
                <p v-else class="mt-2 text-lg font-semibold text-slate-300">Notas não informadas</p>
            </div>
        </div>

        <div v-if="gravity !== null || urgency !== null || trend !== null" class="mt-5 grid grid-cols-3 gap-2">
            <div v-for="item in [{ label: 'G', value: gravity }, { label: 'U', value: urgency }, { label: 'T', value: trend }]" :key="item.label" class="rounded-xl bg-white/8 px-3 py-2 text-center">
                <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ item.label }}</div>
                <div class="mt-1 text-lg font-semibold">{{ item.value ?? '—' }}</div>
            </div>
        </div>

        <p class="mt-4 border-t border-white/10 pt-3 text-xs leading-5 text-slate-300">O produto G×U×T e sua relação com a classificação principal serão definidos posteriormente.</p>
    </div>
</template>
