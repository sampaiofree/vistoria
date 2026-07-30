<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionForm from '@/components/domain/inspections/InspectionForm.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

defineProps({
    inspection: {
        type: Object,
        required: true,
    },
    action: {
        type: String,
        required: true,
    },
    cancel_url: {
        type: String,
        required: true,
    },
    inspection_types: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppLayout
        title="Editar inspeção"
        subtitle="Somente o planejamento técnico permanece editável enquanto a inspeção estiver planejada."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ inspection.number }}</h2>
                        <InspectionStatusBadge :status="inspection.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ inspection.equipment.tag }} — {{ inspection.equipment.name }}
                    </p>
                    <p v-if="inspection.equipment.client" class="text-sm text-slate-500">
                        {{ inspection.equipment.client.name }}
                    </p>
                    <p v-if="inspection.equipment.unit" class="text-sm text-slate-500">
                        {{ inspection.equipment.unit.name }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="cancel_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Voltar
                    </Link>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <InspectionForm
                :action="action"
                :cancel-url="cancel_url"
                :inspection="inspection"
                :inspection-types="inspection_types"
                mode="edit"
                submit-label="Salvar alterações"
            />
        </section>
    </AppLayout>
</template>
