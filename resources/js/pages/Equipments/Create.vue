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
    abc_options: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    maintenance_plan_code: '',
    maintenance_item_code: '',
    area_code: '',
    area_name: '',
    subarea_code: '',
    subarea_name: '',
    task_list_group: '',
    task_list_group_counter: '',
    tag: '',
    defect_code_prefix: '',
    name: '',
    description: '',
    abc_code: '',
    installation_location: '',
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
        subtitle="Cadastre os dados do ativo para o cliente configurado."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <EquipmentForm
                :form="form"
                :abc-options="abc_options"
                :cancel-url="cancel_url"
                submit-label="Criar equipamento"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
