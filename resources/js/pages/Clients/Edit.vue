<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import ClientForm from '@/components/domain/clients/ClientForm.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const props = defineProps({
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
    name: props.client.name ?? '',
    legal_name: props.client.legal_name ?? '',
    document: props.client.document ?? '',
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    notes: props.client.notes ?? '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Editar cliente"
        subtitle="Atualize os dados cadastrais sem alterar a organizacao do registro."
    >
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-lg font-semibold text-slate-900">
                    {{ client.name }}
                </div>
                <StatusBadge :status="client.status ?? 'active'" />
            </div>
            <div v-if="client.document" class="mt-2 text-sm text-slate-500">
                Documento {{ client.document }}
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <ClientForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Salvar alteracoes"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
