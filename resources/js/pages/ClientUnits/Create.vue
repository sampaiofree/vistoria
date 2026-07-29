<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import ClientUnitForm from '@/components/domain/client-units/ClientUnitForm.vue';

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
    name: '',
    code: '',
    timezone: 'America/Sao_Paulo',
    address_line: '',
    address_number: '',
    district: '',
    postal_code: '',
    city: '',
    state: '',
    country_code: 'BR',
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
        title="Nova unidade"
        :subtitle="`Cliente atual: ${client.name}`"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <ClientUnitForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Criar unidade"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
