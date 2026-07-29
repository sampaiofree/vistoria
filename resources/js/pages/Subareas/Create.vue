<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import SubareaForm from '@/components/domain/subareas/SubareaForm.vue';

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
    unit: {
        type: Object,
        required: true,
    },
    area: {
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
        title="Nova subarea"
        :subtitle="`${client.name} / ${unit.name} / ${area.name}`"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <SubareaForm
                :form="form"
                :cancel-url="cancel_url"
                submit-label="Criar subarea"
                @submit="submit"
            />
        </section>
    </AppLayout>
</template>
