<script setup>
defineProps({
    index: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    contentClass: {
        type: String,
        default: 'mt-5',
    },
});
</script>

<template>
    <section>
        <div class="border-b border-slate-200 pb-3">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">{{ index }}</p>
            <h3 class="mt-1 text-xl font-semibold text-slate-950"><slot name="title">{{ title }}</slot></h3>
        </div>
        <div :class="contentClass">
            <slot />
        </div>
    </section>
</template>
