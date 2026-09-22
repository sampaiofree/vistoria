<script setup>
import { computed } from 'vue';

const props = defineProps({
    node: { type: Object, required: true },
});

const isBold = computed(() => (props.node.marks || []).some((mark) => mark.type === 'bold'));
const isItalic = computed(() => (props.node.marks || []).some((mark) => mark.type === 'italic'));
const isPendingRed = computed(() => (props.node.marks || []).some((mark) => mark.type === 'textColor' && mark.attrs?.color === '#DC2626'));
</script>

<template>
    <br v-if="node.type === 'hardBreak'">
    <span v-else :class="{ 'text-red-600': isPendingRed }">
        <strong v-if="isBold && isItalic"><em>{{ node.text }}</em></strong>
        <strong v-else-if="isBold">{{ node.text }}</strong>
        <em v-else-if="isItalic">{{ node.text }}</em>
        <template v-else>{{ node.text }}</template>
    </span>
</template>
