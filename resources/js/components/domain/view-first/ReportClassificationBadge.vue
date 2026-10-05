<script setup>
import { computed } from 'vue';

const props = defineProps({
    code: { type: String, default: null },
    color: { type: String, default: null },
});

const badgeStyle = computed(() => {
    if (!props.code || props.code === '—' || !/^#[0-9a-f]{6}$/i.test(props.color ?? '')) return {};

    // Choose the higher-contrast text without changing the saved background.
    const channels = [1, 3, 5].map((index) => {
        const value = parseInt(props.color.slice(index, index + 2), 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance = channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    return { backgroundColor: props.color, color: luminance > 0.179 ? '#000000' : '#ffffff' };
});
</script>

<template>
    <span class="report-classification-badge inline-flex items-center whitespace-nowrap rounded-md border border-black/15 bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700" :style="badgeStyle">{{ code || '—' }}</span>
</template>
