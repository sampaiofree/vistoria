<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ transition: { type: Object, required: true } });
const form = useForm({ justification: '' });
const needsJustification = computed(() => props.transition.requires_justification === true);
const hasCorrectionMessage = computed(() => props.transition.correction_message === true);
const errorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);
function submit() { form.post(props.transition.action, { preserveScroll: true, onSuccess: () => form.reset() }); }
</script>
<template>
    <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="submit">
        <div class="font-semibold text-slate-900">{{ transition.label }}</div>
        <p v-if="transition.description" class="mt-1 text-sm text-slate-500">{{ transition.description }}</p>
        <label v-if="needsJustification" class="mt-3 block text-sm font-medium text-slate-700">Justificativa obrigatória<textarea v-model="form.justification" required rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><span v-if="form.errors.justification" class="text-xs text-rose-600">{{ form.errors.justification }}</span></label>
        <label v-else-if="hasCorrectionMessage" class="mt-3 block text-sm font-medium text-slate-700">Mensagem geral para o {{ transition.correction_recipient || 'Inspetor' }} <span class="font-normal text-slate-500">(opcional se houver avaria marcada)</span><textarea v-model="form.justification" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><span v-if="form.errors.justification" class="text-xs text-rose-600">{{ form.errors.justification }}</span></label>
        <div v-if="errorMessages.length" role="alert" aria-live="assertive" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            <p class="font-semibold">Não foi possível concluir esta ação.</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
        </div>
        <button :disabled="form.processing" class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-white" :class="transition.key === 'cancel' ? 'bg-rose-600' : 'bg-teal-600'">{{ transition.label }}</button>
    </form>
</template>
