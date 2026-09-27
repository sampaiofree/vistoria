<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';
import InspectionQuantitativeWorksheet from '@/components/domain/inspections/InspectionQuantitativeWorksheet.vue';

defineProps({
    inspection: { type: Object, required: true },
    worksheet: { type: Object, required: true },
    export_url: { type: String, required: true },
    tabs: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="Quantitativo" :subtitle="[inspection.number, worksheet.header.tag.display].filter(Boolean).join(' · ')" wide>
        <template #actions>
            <InspectionStatusBadge :status="inspection.status" />
            <Link :href="inspection.overview_url" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300">Voltar</Link>
            <a :href="export_url" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">Exportar XLS</a>
        </template>

        <div class="lg:hidden"><InspectionTabs :tabs="tabs" active="quantitative" /></div>

        <section class="mt-6 min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
                <p class="text-sm text-slate-600">Uma linha por avaria publicada e ativa. Dados somente para leitura.</p>
                <span class="text-sm font-semibold text-slate-700">{{ worksheet.rows.length }} avaria(s)</span>
            </div>
            <InspectionQuantitativeWorksheet :worksheet="worksheet" />
        </section>
    </AppLayout>
</template>
