<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import SubareaForm from '@/components/domain/subareas/SubareaForm.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const props = defineProps({
    subarea: {
        type: Object,
        required: true,
    },
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
    name: props.subarea.name ?? '',
    code: props.subarea.code ?? '',
    description: props.subarea.description ?? '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Editar subarea"
        subtitle="Atualize os dados sem trocar a area pai pelo formulario comum."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-semibold text-slate-900">
                    {{ subarea.name }}
                </div>
                <StatusBadge :status="subarea.status ?? 'active'" />
            </div>
            <div class="mt-2 text-sm text-slate-500">
                {{ client.name }} / {{ unit.name }} / {{ area.name }}
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <SubareaForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Salvar alteracoes"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
