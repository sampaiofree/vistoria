<script setup>
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionForm from '@/components/domain/inspections/InspectionForm.vue';
defineProps({ action: { type: String, required: true }, cancel_url: { type: String, required: true }, equipment: { type: Array, required: true }, released_inspections: { type: Array, required: true } });
</script>
<template><AppLayout title="Nova inspeção" subtitle="Crie uma inspeção inicial ou vincule uma reinspeção liberada."><section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><InspectionForm :action="action" :cancel-url="cancel_url" :equipment="equipment" :released-inspections="released_inspections" /></section></AppLayout></template>
