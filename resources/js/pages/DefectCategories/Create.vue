<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectCategoryForm from '@/components/domain/classification/DefectCategoryForm.vue';

const props = defineProps({ action: { type: String, required: true }, cancel_url: { type: String, required: true } });
const form = useForm({ name: '', code: '', description: '', position: 1, requires_location_map: false, confirm_location_map_requirement: false });
function submit() { form.post(props.action); }
</script>

<template><AppLayout title="Nova categoria" subtitle="Cadastre uma categoria de avaria para a organização."><section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><DefectCategoryForm :form="form" :cancel-url="cancel_url" submit-label="Criar categoria" @submit="submit" /></section></AppLayout></template>
