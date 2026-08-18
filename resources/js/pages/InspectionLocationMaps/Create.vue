<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionLocationMapForm from '@/components/domain/inspection-locations/InspectionLocationMapForm.vue';

const props = defineProps({
    inspection: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    action: { type: String, required: true },
    cancel_url: { type: String, required: true },
});
const selectedCategory = new URLSearchParams(window.location.search).get('category') || '';
const form = useForm({ title: '', description: '', position: 1, defect_category_id: selectedCategory });
function submit() { form.post(props.action); }
</script>

<template>
    <AppLayout title="Novo mapa de localização" :subtitle="`${inspection.equipment.tag} · ${inspection.number || 'Inspeção'}`">
        <section class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <InspectionLocationMapForm :form="form" :categories="categories" :cancel-url="cancel_url" @submit="submit" />
        </section>
    </AppLayout>
</template>
