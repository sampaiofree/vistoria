<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    photoUrl: { type: String, default: null },
});

const failed = ref(false);
const initials = computed(() => props.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || 'V');

watch(() => props.photoUrl, () => { failed.value = false; });
</script>

<template>
    <span class="inline-flex shrink-0 items-center justify-center overflow-hidden text-xs font-semibold">
        <img v-if="photoUrl && !failed" :src="photoUrl" alt="" class="h-full w-full object-cover object-center" @error="failed = true">
        <span v-else>{{ initials }}</span>
    </span>
</template>
