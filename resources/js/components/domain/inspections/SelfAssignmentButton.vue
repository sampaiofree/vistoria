<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ capability: { type: Object, default: null } });
const form = useForm({});
const submitting = ref(false);
const selected = ref([]);

function toggle(value) {
    selected.value = selected.value.includes(value)
        ? selected.value.filter((item) => item !== value)
        : [...selected.value, value];
}

function submit() {
    if (submitting.value || !props.capability?.action) return;
    if (props.capability.options?.length > 1 && selected.value.length === 0) return;
    submitting.value = true;
    try {
        if (props.capability.requires_selection) {
            form.responsibilities = props.capability.options.length === 1
                ? [props.capability.options[0].value]
                : selected.value;
        }
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
    <button v-if="capability && (capability.options?.length ?? 1) <= 1" type="button" :disabled="submitting" :aria-busy="submitting" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-wait disabled:opacity-60" @click="submit">
        {{ submitting ? 'Assumindo…' : capability.label }}
    </button>
    <div v-else-if="capability" class="inline-flex flex-wrap items-center gap-2">
        <fieldset class="inline-flex items-center gap-3 text-sm">
            <legend class="sr-only">Funções para assumir</legend>
            <label v-for="option in capability.options" :key="option.value" class="inline-flex items-center gap-1">
                <input type="checkbox" :checked="selected.includes(option.value)" :disabled="submitting" @change="toggle(option.value)">
                {{ option.label }}
            </label>
        </fieldset>
        <button type="button" :disabled="submitting || (capability.options?.length > 1 && selected.length === 0)" :aria-busy="submitting" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-wait disabled:opacity-60" @click="submit">
            {{ submitting ? 'Assumindo…' : capability.label }}
        </button>
    </div>
</template>
