<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                Vistoria
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                {{ description }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <slot name="actions" />
        </div>
    </section>
</template>
