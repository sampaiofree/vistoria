<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AreaForm from '@/components/domain/areas/AreaForm.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const props = defineProps({
    area: {
        type: Object,
        required: true,
    },
    unit: {
        type: Object,
        required: true,
    },
    client: {
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
    name: props.area.name ?? '',
    code: props.area.code ?? '',
    description: props.area.description ?? '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Editar area"
        subtitle="Atualize a area mantendo a unidade pai no formulario comum."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-semibold text-slate-900">
                    {{ area.name }}
                </div>
                <StatusBadge :status="area.status ?? 'active'" />
            </div>
            <div class="mt-2 text-sm text-slate-500">
                {{ client.name }} / {{ unit.name }}
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <AreaForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Salvar alteracoes"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
