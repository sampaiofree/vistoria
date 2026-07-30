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
    client_id: props.equipment.client_id ?? '',
    client_unit_id: props.equipment.client_unit_id ?? '',
    area_id: props.equipment.area_id ?? '',
    subarea_id: props.equipment.subarea_id ?? '',
    tag: props.equipment.tag ?? '',
    name: props.equipment.name ?? '',
    description: props.equipment.description ?? '',
    manufacturer: props.equipment.manufacturer ?? '',
    model: props.equipment.model ?? '',
    serial_number: props.equipment.serial_number ?? '',
    asset_code: props.equipment.asset_code ?? '',
    abc_code: props.equipment.abc_code ?? '',
    installation_location: props.equipment.installation_location ?? '',
    commissioned_at: props.equipment.commissioned_at ?? '',
    notes: props.equipment.notes ?? '',
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
                        TAG {{ equipment.tag }} · {{ equipment.public_id }}
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
                :clients="clients"
                :units="units"
                :areas="areas"
                :subareas="subareas"
                :cancel-url="cancel_url"
                submit-label="Salvar alterações"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
