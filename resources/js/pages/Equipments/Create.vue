<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import EquipmentForm from '@/components/domain/equipments/EquipmentForm.vue';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    cancel_url: {
        type: String,
        required: true,
    },
    clients: {
        type: Array,
        required: true,
    },
    units: {
        type: Array,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
    subareas: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    client_id: '',
    client_unit_id: '',
    area_id: '',
    subarea_id: '',
    tag: '',
    defect_code_prefix: '',
    name: '',
    description: '',
    manufacturer: '',
    model: '',
    serial_number: '',
    asset_code: '',
    abc_code: '',
    installation_location: '',
    commissioned_at: '',
    notes: '',
});

function submit() {
    form.post(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Novo equipamento"
        subtitle="Cadastre o ativo e associe-o à hierarquia operacional correta."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <EquipmentForm
                :form="form"
                :clients="clients"
                :units="units"
                :areas="areas"
                :subareas="subareas"
                :cancel-url="cancel_url"
                submit-label="Criar equipamento"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
