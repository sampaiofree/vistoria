<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import EquipmentForm from '@/components/domain/equipments/EquipmentForm.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const props = defineProps({
    equipment: {
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
});

const form = useForm({
    maintenance_plan_code: props.equipment.maintenance_plan_code ?? '',
    maintenance_item_code: props.equipment.maintenance_item_code ?? '',
    area_code: props.equipment.area_code ?? '',
    area_name: props.equipment.area_name ?? '',
    subarea_code: props.equipment.subarea_code ?? '',
    subarea_name: props.equipment.subarea_name ?? '',
    task_list_group: props.equipment.task_list_group ?? '',
    task_list_group_counter: props.equipment.task_list_group_counter ?? '',
    tag: props.equipment.tag ?? '',
    defect_code_prefix: props.equipment.defect_code_prefix ?? '',
    name: props.equipment.name ?? '',
    description: props.equipment.description ?? '',
    abc_code: props.equipment.abc_code ?? '',
    installation_location: props.equipment.installation_location ?? '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Editar equipamento"
        subtitle="Atualize o cadastro cadastral e preserve o histórico do ativo."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ equipment.name }}</h2>
                        <StatusBadge :status="equipment.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        Item manutenção {{ equipment.maintenance_item_code || 'Não informado' }} · TAG {{ equipment.tag }} · {{ equipment.public_id }}
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
            <EquipmentForm
                :form="form"
                :prefix-editable="equipment.can_edit_defect_code_prefix"
                :cancel-url="cancel_url"
                submit-label="Salvar alterações"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
