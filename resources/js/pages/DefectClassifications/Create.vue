<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectClassificationForm from '@/components/domain/classification/DefectClassificationForm.vue';

const props = defineProps({ category: { type: Object, required: true }, action: { type: String, required: true }, cancel_url: { type: String, required: true } });
const form = useForm({ code: '', name: '', description: '', color: '', position: 1, severity_rank: null, lower_limit: '', upper_limit: '' });
function submit() { form.post(props.action); }
</script>

<template><AppLayout title="Nova classificação" subtitle="Adicione uma classificação à categoria selecionada."><section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><DefectClassificationForm :form="form" :category="category" :cancel-url="cancel_url" submit-label="Criar classificação" @submit="submit" /></section></AppLayout></template>
