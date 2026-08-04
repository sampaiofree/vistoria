<script setup>
import { computed } from 'vue';

const props = defineProps({
    progress: {
        type: Object,
        default: () => ({ completed: 0, total: 0, percentage: 0 }),
    },
    label: {
        type: String,
        default: 'Progresso das avaliações',
    },
    dark: {
        type: Boolean,
        default: false,
    },
    countSuffix: {
        type: String,
        default: '',
    },
});

const completed = computed(() => Number(props.progress?.completed ?? 0));
const total = computed(() => Number(props.progress?.total ?? 0));
const percentage = computed(() => {
    const supplied = Number(props.progress?.percentage ?? props.progress?.progress_percent);

    if (Number.isFinite(supplied)) {
        return Math.min(100, Math.max(0, supplied));
    }

    return total.value === 0 ? 0 : Math.round((completed.value / total.value) * 100);
});
</script>

<template>
    <div class="min-w-0">
        <div class="flex items-center justify-between gap-4 text-xs font-semibold" :class="dark ? 'text-slate-300' : 'text-slate-500'">
            <span>{{ label }}</span>
            <span :class="dark ? 'text-white' : 'text-slate-900'">
                {{ completed }}/{{ total }}<template v-if="countSuffix"> {{ countSuffix }}</template> · {{ percentage }}%
            </span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full" :class="dark ? 'bg-white/10' : 'bg-slate-100'" role="progressbar" :aria-label="label" :aria-valuenow="percentage" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-teal-400 transition-[width]" :style="{ width: `${percentage}%` }" />
        </div>
    </div>
</template>
