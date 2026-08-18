<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectCategoryForm from '@/components/domain/classification/DefectCategoryForm.vue';

const props = defineProps({ category: { type: Object, required: true }, activation_impact: { type: Object, required: true }, action: { type: String, required: true }, cancel_url: { type: String, required: true } });
const form = useForm({ name: props.category.name, code: props.category.code, description: props.category.description ?? '', position: props.category.position, requires_location_map: props.category.requires_location_map, confirm_location_map_requirement: false });
function submit() { form.patch(props.action); }
</script>

<template><AppLayout title="Editar categoria" subtitle="O código não pode mudar após o primeiro uso."><section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><DefectCategoryForm :form="form" :cancel-url="cancel_url" :activation-impact="activation_impact" :initially-requires-location-map="category.requires_location_map" submit-label="Salvar categoria" @submit="submit" /></section></AppLayout></template>
