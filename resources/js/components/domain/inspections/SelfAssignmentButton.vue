<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ capability: { type: Object, default: null } });
const form = useForm({});
const submitting = ref(false);

function submit() {
    if (submitting.value || !props.capability?.action) return;
    submitting.value = true;
    try {
        form.post(props.capability.action, {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        });
    } catch (error) {
        submitting.value = false;
        throw error;
    }
}
</script>

<template>
    <button v-if="capability" type="button" :disabled="submitting" :aria-busy="submitting" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-wait disabled:opacity-60" @click="submit">
        {{ submitting ? 'Assumindo…' : capability.label }}
    </button>
</template>
