<script setup>
import { Link } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

defineProps({
    inspections: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Rastreabilidade</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-950">Histórico de inspeções</h3>
            <p class="mt-1 text-sm text-slate-500">Evolução técnica e situação de cada ciclo do equipamento.</p>
        </div>

        <div v-if="inspections.length" class="mt-6 space-y-0">
            <article
                v-for="(inspection, index) in inspections"
                :key="inspection.public_id"
                class="relative grid grid-cols-[1.25rem_minmax(0,1fr)] gap-3 pb-6 last:pb-0"
            >
                <div class="relative flex justify-center">
                    <span
                        v-if="index < inspections.length - 1"
                        class="absolute bottom-[-1.5rem] top-3 w-px bg-slate-200"
                        aria-hidden="true"
                    />
                    <span
                        class="relative mt-1.5 h-2.5 w-2.5 rounded-full ring-4"
                        :class="inspection.is_current ? 'bg-teal-500 ring-teal-50' : 'bg-slate-300 ring-slate-50'"
                        aria-hidden="true"
                    />
                </div>

                <div class="min-w-0 rounded-xl border p-4" :class="inspection.is_current ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-slate-50/60'">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link :href="inspection.show_url" class="font-semibold text-slate-950 transition hover:text-teal-700">
                                    {{ inspection.number }}
                                </Link>
                                <span v-if="inspection.is_current" class="rounded-full bg-teal-600 px-2 py-0.5 text-[0.68rem] font-semibold uppercase tracking-wide text-white">
                                    Atual
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ inspection.inspection_type_label }}
                                <span v-if="inspection.date_label"> · {{ inspection.date_label }}</span>
                            </p>
                        </div>
                        <InspectionStatusBadge :status="inspection.status" />
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
            <p class="text-sm font-medium text-slate-700">Nenhuma inspeção registrada.</p>
            <p class="mt-1 text-xs text-slate-500">O histórico aparecerá após o primeiro planejamento.</p>
        </div>
    </section>
</template>
