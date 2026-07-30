<script setup>
defineProps({
    title: {
        type: String,
        default: 'Não foi possível carregar este bloco.',
    },
    description: {
        type: String,
        default: 'Tente novamente. Os demais dados do dashboard continuam disponíveis.',
    },
    retrying: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['retry']);
</script>

<template>
    <section
        role="alert"
        class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"
    >
        <h2 class="text-sm font-semibold">
            {{ title }}
        </h2>
        <p class="mt-1 text-sm leading-6 text-rose-700">
            {{ description }}
        </p>
        <button
            type="button"
            class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-800 transition hover:border-rose-400 hover:bg-rose-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/70 disabled:cursor-wait disabled:opacity-60"
            :disabled="retrying"
            @click="$emit('retry')"
        >
            {{ retrying ? 'Tentando novamente…' : 'Tentar novamente' }}
        </button>
    </section>
</template>
