<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import ClientUnitForm from '@/components/domain/client-units/ClientUnitForm.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const props = defineProps({
    unit: {
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
    name: props.unit.name ?? '',
    code: props.unit.code ?? '',
    timezone: props.unit.timezone ?? '',
    address_line: props.unit.address_line ?? '',
    address_number: props.unit.address_number ?? '',
    district: props.unit.district ?? '',
    postal_code: props.unit.postal_code ?? '',
    city: props.unit.city ?? '',
    state: props.unit.state ?? '',
    country_code: props.unit.country_code ?? 'BR',
    notes: props.unit.notes ?? '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Editar unidade"
        subtitle="Ajuste os dados sem trocar o cliente pai pelo formulario comum."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-semibold text-slate-900">
                    {{ unit.name }}
                </div>
                <StatusBadge :status="unit.status ?? 'active'" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <ClientUnitForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Salvar alteracoes"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
