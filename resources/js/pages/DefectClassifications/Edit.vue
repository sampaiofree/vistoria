<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectClassificationForm from '@/components/domain/classification/DefectClassificationForm.vue';

const props = defineProps({ classification: { type: Object, required: true }, category: { type: Object, required: true }, action: { type: String, required: true }, cancel_url: { type: String, required: true } });
const form = useForm({ code: props.classification.code, name: props.classification.name, description: props.classification.description ?? '', color: props.classification.color ?? '', position: props.classification.position, severity_rank: props.classification.severity_rank, lower_limit: props.classification.lower_limit ?? '', upper_limit: props.classification.upper_limit ?? '' });
function submit() { form.patch(props.action); }
</script>

<template><AppLayout title="Editar classificação" :subtitle="`${category.name} · ${category.code}`"><section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><DefectClassificationForm :form="form" :category="category" :cancel-url="cancel_url" submit-label="Salvar classificação" @submit="submit" /></section></AppLayout></template>
