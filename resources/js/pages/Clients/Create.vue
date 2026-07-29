<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import ClientForm from '@/components/domain/clients/ClientForm.vue';

const props = defineProps({
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
    name: '',
    legal_name: '',
    document: '',
    email: '',
    phone: '',
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
        title="Novo cliente"
        subtitle="Crie o cadastro da empresa atendida pela organizacao atual."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <ClientForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Criar cliente"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
