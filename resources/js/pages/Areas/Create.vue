<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AreaForm from '@/components/domain/areas/AreaForm.vue';

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
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
    name: '',
    code: '',
    description: '',
});

function submit() {
    form.post(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Nova area"
        :subtitle="`Cliente: ${client.name} | Unidade: ${unit.name}`"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <AreaForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Criar area"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
