<script setup>
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionBatchForm from '@/components/domain/inspections/InspectionBatchForm.vue';

defineProps({
    preview_action: {
        type: String,
        required: true,
    },
    confirm_action: {
        type: String,
        required: true,
    },
    cancel_url: {
        type: String,
        required: true,
    },
    equipment_search_url: {
        type: String,
        required: true,
    },
    selected_equipment: {
        type: Array,
        required: true,
    },
    inspectors: {
        type: Array,
        required: true,
    },
    preview: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <AppLayout
        title="Planejar inspeções"
        subtitle="Inclua os equipamentos, períodos e Inspetores responsáveis pela verificação."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <InspectionBatchForm
                :preview-action="preview_action"
                :confirm-action="confirm_action"
                :cancel-url="cancel_url"
                :equipment-search-url="equipment_search_url"
                :selected-equipment="selected_equipment"
                :inspectors="inspectors"
                :preview="preview"
            />
        </section>
    </AppLayout>
</template>
