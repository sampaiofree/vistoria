<script setup>
import { Link } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import UiIcon from '@/components/ui/UiIcon.vue';

defineProps({
    activities: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const skeletonActivities = [1, 2, 3, 4, 5, 6];
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">
                    Atividades recentes
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Últimas mudanças relevantes no fluxo.
                </p>
            </div>
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                <UiIcon name="activity" class="h-5 w-5" />
            </span>
        </div>

        <span v-if="loading" class="sr-only" role="status">Carregando atividades recentes.</span>
        <div v-if="loading" aria-hidden="true" class="mt-5 space-y-3">
            <div v-for="item in skeletonActivities" :key="item" class="animate-pulse rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="h-3 w-28 rounded bg-slate-200" />
                <div class="mt-3 h-4 w-5/6 rounded bg-slate-200" />
                <div class="mt-2 h-3 w-2/3 rounded bg-slate-100" />
            </div>
        </div>

        <div v-else-if="activities.length > 0" class="mt-5 space-y-3">
            <article
                v-for="activity in activities"
                :key="activity.id"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <span>{{ activity.actor }}</span>
                            <span>·</span>
                            <span>{{ activity.time_label }}</span>
                        </div>
                        <Link :href="activity.inspection.href" class="mt-2 block text-sm font-semibold text-slate-900 transition hover:text-teal-700">
                            {{ activity.description }}
                        </Link>
                        <p class="mt-1 text-xs text-slate-500">
                            Inspeção {{ activity.inspection.number }}
                        </p>
                    </div>

                    <InspectionStatusBadge :status="activity.status" />
                </div>
            </article>
        </div>

        <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">
            Nenhuma atividade recente para exibir.
        </div>
    </section>
</template>
