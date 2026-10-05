<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ transition: { type: Object, required: true } });
const form = useForm({ justification: '', correction_target: 'inspector' });
const needsJustification = computed(() => props.transition.requires_justification === true);
const hasCorrectionMessage = computed(() => props.transition.correction_message === true);
const correctionTargets = computed(() => props.transition.correction_targets ?? []);
const correctionRecipient = computed(() => correctionTargets.value.find(target => target.value === form.correction_target)?.label ?? props.transition.correction_recipient ?? 'Inspetor');
const plannerMessageRequired = computed(() => correctionTargets.value.length > 0 && form.correction_target === 'planner' && !props.transition.marked_planner_general_request);
const errorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);
function submit() {
    form.transform(data => correctionTargets.value.length ? data : { justification: data.justification })
        .post(props.transition.action, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
<template>
    <form class="rounded-xl border border-slate-200 p-4 bg-white" @submit.prevent="submit">
        <div class="font-semibold text-slate-900">{{ transition.label }}</div>
        <p v-if="transition.description" class="mt-1 text-sm text-slate-500">{{ transition.description }}</p>
        <label v-if="needsJustification" class="mt-3 block text-sm font-medium text-slate-700">Justificativa obrigatória<textarea v-model="form.justification" required rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea><span v-if="form.errors.justification" class="text-xs text-rose-600">{{ form.errors.justification }}</span></label>
        <template v-else-if="hasCorrectionMessage">
            <label v-if="correctionTargets.length" class="mt-3 block text-sm font-medium text-slate-700">
                Enviar correção geral para
                <select v-model="form.correction_target" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option v-for="target in correctionTargets" :key="target.value" :value="target.value">{{ target.label }}</option>
                </select>
                <span v-if="form.errors.correction_target" class="text-xs text-rose-600">{{ form.errors.correction_target }}</span>
            </label>
            <label class="mt-3 block text-sm font-medium text-slate-700">
                Mensagem geral para o {{ correctionRecipient }}
                <span v-if="form.correction_target !== 'planner' || !correctionTargets.length" class="font-normal text-slate-500">(opcional se houver avaria marcada)</span>
                <span v-else-if="transition.marked_planner_general_request" class="font-normal text-slate-500">(opcional se já houver solicitação geral marcada)</span>
                <textarea v-model="form.justification" :required="plannerMessageRequired" minlength="10" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                <span v-if="form.errors.justification" class="text-xs text-rose-600">{{ form.errors.justification }}</span>
            </label>
        </template>
        <div v-if="errorMessages.length" role="alert" aria-live="assertive" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            <p class="font-semibold">Não foi possível concluir esta ação.</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
        </div>
        <button :disabled="form.processing" class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-white" :class="transition.key === 'cancel' ? 'bg-rose-600' : 'bg-teal-600'">{{ transition.label }}</button>
    </form>
</template>
