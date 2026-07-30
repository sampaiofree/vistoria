<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ transition: { type: Object, required: true } });
const form = useForm({ justification: '' });
const needsJustification = computed(() => props.transition.requires_justification === true);
function submit() { form.post(props.transition.action, { preserveScroll: true, onSuccess: () => form.reset() }); }
</script>
<template>
    <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="submit">
        <div class="font-semibold text-slate-900">{{ transition.label }}</div>
        <p v-if="transition.description" class="mt-1 text-sm text-slate-500">{{ transition.description }}</p>
        <label v-if="needsJustification" class="mt-3 block text-sm font-medium text-slate-700">Justificativa obrigatória<textarea v-model="form.justification" required rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><span v-if="form.errors.justification" class="text-xs text-rose-600">{{ form.errors.justification }}</span></label>
        <button :disabled="form.processing" class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-white" :class="transition.key === 'cancel' ? 'bg-rose-600' : 'bg-teal-600'">{{ transition.label }}</button>
    </form>
</template>
