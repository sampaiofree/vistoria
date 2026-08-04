<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AssessmentProgress from '@/components/domain/view-first/AssessmentProgress.vue';
import UiIcon from '@/components/ui/UiIcon.vue';

const props = defineProps({
    inspection: {
        type: Object,
        default: null,
    },
});

const progress = computed(() => props.inspection?.progress ?? { completed: 0, total: 0, percentage: 0 });
const inspectionUrl = computed(() => props.inspection?.show_url ?? props.inspection?.next_action?.href ?? '');
const actionLabel = computed(() => (
    props.inspection?.inspection_type === 'reinspection'
        ? 'Continuar reinspeção'
        : 'Abrir inspeção'
));
</script>

<template>
    <section
        v-if="inspection"
        class="relative isolate mb-6 overflow-hidden rounded-3xl bg-[#081a2f] p-5 text-white shadow-xl shadow-slate-950/10 sm:p-7"
        aria-labelledby="featured-inspection-title"
    >
        <div class="pointer-events-none absolute -right-24 -top-32 h-72 w-72 rounded-full border border-teal-300/20 bg-teal-400/10" />
        <div class="pointer-events-none absolute -bottom-44 right-12 h-72 w-72 rounded-full border border-white/10" />

        <div class="relative grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-teal-200">
                    <span class="inline-flex items-center gap-2 rounded-full border border-teal-300/20 bg-teal-300/10 px-3 py-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-300" />
                        Inspeção em andamento
                    </span>
                    <span v-if="inspection.inspection_type_label" class="text-slate-300">
                        {{ inspection.inspection_type_label }}
                    </span>
                </div>

                <p class="mt-5 text-sm font-medium text-slate-300">
                    {{ inspection.client?.name ?? 'Cliente' }}
                    <span v-if="inspection.unit?.name"> · {{ inspection.unit.name }}</span>
                </p>
                <h2 id="featured-inspection-title" class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ inspection.equipment?.name ?? inspection.number }}
                </h2>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-300">
                    <span v-if="inspection.equipment?.tag" class="font-semibold text-teal-200">
                        TAG {{ inspection.equipment.tag }}
                    </span>
                    <span>{{ inspection.number }}</span>
                    <span v-if="inspection.service_order">OS {{ inspection.service_order }}</span>
                </div>

                <div v-if="progress.total > 0" class="mt-6 max-w-2xl">
                    <AssessmentProgress :progress="progress" count-suffix="concluídas" dark />
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row xl:flex-col">
                <Link
                    v-if="inspectionUrl"
                    :href="inspectionUrl"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-teal-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-teal-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-200"
                >
                    {{ actionLabel }}
                    <UiIcon name="arrow-right" class="h-4 w-4" />
                </Link>
                <Link
                    v-if="inspection.equipment?.show_url"
                    :href="inspection.equipment.show_url"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                >
                    Ver equipamento
                </Link>
            </div>
        </div>
    </section>
</template>
