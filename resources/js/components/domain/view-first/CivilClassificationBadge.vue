<script setup>
import { computed } from 'vue';

const props = defineProps({
    code: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    large: {
        type: Boolean,
        default: false,
    },
    historical: {
        type: Boolean,
        default: false,
    },
});

const meta = computed(() => {
    const definitions = {
        'CV-0': 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-800',
        'CV-1': 'border-red-200 bg-red-50 text-red-800',
        'CV-2': 'border-rose-200 bg-rose-50 text-rose-800',
        'CV-3': 'border-orange-200 bg-orange-50 text-orange-800',
        'CV-4': 'border-amber-200 bg-amber-50 text-amber-800',
        'CV-5': 'border-emerald-200 bg-emerald-50 text-emerald-800',
    };

    return definitions[props.code] ?? 'border-slate-200 bg-slate-100 text-slate-600';
});
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border font-bold tracking-wide"
        :class="[meta, large ? 'px-4 py-2 text-sm' : 'px-2.5 py-1 text-xs']"
    >
        {{ code || 'Sem CV' }}<span v-if="label" class="ml-1 font-medium">· {{ label }}</span><span v-if="historical" class="ml-1 font-medium">· histórica</span>
    </span>
</template>
