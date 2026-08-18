<script setup>
import { computed } from 'vue';

const props = defineProps({
    node: { type: Object, required: true },
});

const isBold = computed(() => (props.node.marks || []).some((mark) => mark.type === 'bold'));
const isItalic = computed(() => (props.node.marks || []).some((mark) => mark.type === 'italic'));
</script>

<template>
    <br v-if="node.type === 'hardBreak'">
    <strong v-else-if="isBold && isItalic"><em>{{ node.text }}</em></strong>
    <strong v-else-if="isBold">{{ node.text }}</strong>
    <em v-else-if="isItalic">{{ node.text }}</em>
    <template v-else>{{ node.text }}</template>
</template>
