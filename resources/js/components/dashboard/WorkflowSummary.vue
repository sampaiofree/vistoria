<script setup>
import { Link } from '@inertiajs/vue3';
import UiIcon from '@/components/ui/UiIcon.vue';

defineProps({
    steps: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    companySummary: {
        type: Boolean,
        default: false,
    },
});

const skeletonSteps = [1, 2, 3, 4, 5, 6, 7, 8];
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">
                    Fluxo operacional
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ companySummary ? 'Visão resumida da operação da empresa.' : 'Inspeções relacionadas às suas atribuições.' }}
                </p>
            </div>
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                <UiIcon name="workflow" class="h-5 w-5" />
            </span>
        </div>

        <span v-if="loading" class="sr-only" role="status">Carregando resumo do fluxo.</span>
        <div v-if="loading" aria-hidden="true" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-2">
            <div v-for="step in skeletonSteps" :key="step" class="animate-pulse rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="h-3 w-20 rounded bg-slate-200" />
                <div class="mt-4 h-7 w-12 rounded bg-slate-200" />
                <div class="mt-3 h-3 w-24 rounded bg-slate-100" />
            </div>
        </div>

        <div v-else-if="steps.length > 0" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-2">
            <Link
                v-for="step in steps"
                :key="step.key"
                :href="step.href"
                class="group rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/70"
            >
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                    {{ step.label }}
                </div>
                <div class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">
                    {{ step.count }}
                </div>
                <div class="mt-2 text-sm font-semibold text-teal-700">
                    Ver etapa
                </div>
            </Link>
        </div>

        <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">
            Nenhum dado de fluxo disponível.
        </div>
    </section>
</template>
