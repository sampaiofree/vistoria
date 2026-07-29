<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    currentStatus: {
        type: String,
        required: true,
    },
    entityLabel: {
        type: String,
        required: true,
    },
});

const form = useForm({
    status: props.currentStatus === 'active' ? 'inactive' : 'active',
});

const buttonLabel = computed(() => (props.currentStatus === 'active' ? 'Inativar' : 'Ativar'));

const buttonClass = computed(() =>
    props.currentStatus === 'active'
        ? 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100'
        : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100',
);

function submit() {
    const confirmed = window.confirm(`Deseja ${buttonLabel.value.toLowerCase()} este ${props.entityLabel}?`);

    if (!confirmed) {
        return;
    }

    form.status = props.currentStatus === 'active' ? 'inactive' : 'active';
    form.patch(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form @submit.prevent="submit">
        <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="buttonClass"
        >
            {{ buttonLabel }}
        </button>
    </form>
</template>
