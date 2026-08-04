<script setup>
defineProps({
    title: {
        type: String,
        default: 'Configuração de demonstração',
    },
    message: {
        type: String,
        default: 'Parâmetros sujeitos à validação técnica.',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <aside
        class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-950"
        :class="compact ? 'px-4 py-3' : 'p-5'"
        role="note"
    >
        <div class="flex items-start gap-3">
            <span
                class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-sm font-black text-amber-800"
                aria-hidden="true"
            >
                i
            </span>
            <div>
                <p class="text-sm font-semibold">{{ title }}</p>
                <p class="mt-1 text-sm leading-6 text-amber-800">{{ message }}</p>
            </div>
        </div>
    </aside>
</template>
