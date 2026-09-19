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
        class="mb-6 rounded-lg border border-slate-200 bg-white p-5 sm:p-6"
        aria-labelledby="featured-inspection-title"
    >
        <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <span class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2.5 py-1 font-medium text-blue-700">
                        Inspeção em andamento
                    </span>
                    <span v-if="inspection.inspection_type_label">
                        {{ inspection.inspection_type_label }}
                    </span>
                </div>

                <p class="mt-4 text-sm text-slate-600">
                    {{ inspection.client?.name ?? 'Cliente' }}
                </p>
                <h2 id="featured-inspection-title" class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">
                    {{ inspection.equipment?.name ?? inspection.number }}
                </h2>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-600">
                    <span v-if="inspection.equipment?.tag" class="font-semibold text-slate-900">
                        TAG {{ inspection.equipment.tag }}
                    </span>
                    <span>{{ inspection.number }}</span>
                    <span v-if="inspection.service_order">OS {{ inspection.service_order }}</span>
                </div>

                <div v-if="progress.total > 0" class="mt-6 max-w-2xl">
                    <AssessmentProgress :progress="progress" count-suffix="concluídas" />
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row xl:flex-col">
                <Link
                    v-if="inspectionUrl"
                    :href="inspectionUrl"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2"
                >
                    {{ actionLabel }}
                    <UiIcon name="arrow-right" class="h-4 w-4" />
                </Link>
                <Link
                    v-if="inspection.equipment?.show_url"
                    :href="inspection.equipment.show_url"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                >
                    Ver equipamento
                </Link>
            </div>
        </div>
    </section>
</template>
