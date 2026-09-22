<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    request: { type: Object, required: true },
    assessmentOptions: { type: Array, default: () => [] },
    nested: { type: Boolean, default: false },
});

const editOpen = ref(false);
const responseOpen = ref(false);
const replacementOpen = ref(false);
const childOpen = ref(false);
const messageForm = useForm({ request_message: '' });
const responseForm = useForm({ response_message: '' });
const childForm = useForm({ request_message: '', defect_assessment_id: '' });

function submitMessage(url, mode = 'post') {
    messageForm[mode](url, { preserveScroll: true, onSuccess: () => { messageForm.reset(); editOpen.value = false; replacementOpen.value = false; } });
}

function address() {
    responseForm.patch(props.request.address_url, { preserveScroll: true, onSuccess: () => { responseForm.reset(); responseOpen.value = false; } });
}

function createChild() {
    childForm.post(props.request.create_child_url, { preserveScroll: true, onSuccess: () => { childForm.reset(); childOpen.value = false; } });
}

function reopen() { router.patch(props.request.mark_pending_url, {}, { preserveScroll: true }); }
function close() { router.patch(props.request.close_url, {}, { preserveScroll: true }); }
function remove() {
    if (window.confirm('Remover esta marcação de ajuste?')) router.delete(props.request.delete_url, { preserveScroll: true });
}
</script>

<template>
    <article class="rounded-xl border border-amber-200 bg-white/80 p-3" :class="nested ? 'ml-4 border-l-4' : ''">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-950">{{ request.status_label }}</span>
            <span class="text-xs font-semibold text-slate-500">{{ request.requester_label }} → {{ request.responder_label }}</span>
            <span v-if="request.created_at" class="text-xs text-slate-400">{{ request.created_at }}</span>
        </div>
        <p v-if="request.parent" class="mt-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">Desdobramento do apontamento do {{ request.parent.requester_label }}: {{ request.parent.request_message }}</p>
        <p class="mt-3 whitespace-pre-line leading-6"><span class="font-semibold">{{ request.requester_label }}:</span> {{ request.request_message }}</p>
        <p v-if="request.response_message" class="mt-3 whitespace-pre-line rounded-lg bg-teal-50 p-3 leading-6"><span class="font-semibold">Resposta do {{ request.responder_label }}:</span> {{ request.response_message }}</p>

        <div class="mt-3 flex flex-wrap gap-2">
            <button v-if="request.update_url" type="button" class="rounded-lg border border-amber-400 px-3 py-2 text-sm font-semibold text-amber-900" @click="editOpen = !editOpen; messageForm.request_message = request.request_message">Editar mensagem</button>
            <button v-if="request.delete_url" type="button" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700" @click="remove">Remover</button>
            <button v-if="request.address_url" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white" @click="responseOpen = !responseOpen">Responder</button>
            <button v-if="request.mark_pending_url" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="reopen">Voltar para pendente</button>
            <button v-if="request.close_url" type="button" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white" @click="close">Encerrar</button>
            <button v-if="request.replace_url" type="button" class="rounded-lg border border-amber-500 px-3 py-2 text-sm font-semibold text-amber-900" @click="replacementOpen = !replacementOpen; messageForm.reset()">Solicitar novo ajuste</button>
            <button v-if="request.create_child_url" type="button" class="rounded-lg border border-indigo-400 px-3 py-2 text-sm font-semibold text-indigo-900" @click="childOpen = !childOpen; childForm.reset()">Solicitar ajuste ao Inspetor</button>
        </div>

        <form v-if="editOpen" class="mt-3" @submit.prevent="submitMessage(request.update_url, 'patch')">
            <label class="block text-sm font-medium text-slate-800">Mensagem para o {{ request.responder_label }}<textarea v-model="messageForm.request_message" required minlength="10" rows="3" class="mt-1 w-full rounded-lg border border-amber-300 px-3 py-2" /></label>
            <p v-if="messageForm.errors.request_message" class="mt-1 text-xs text-rose-700">{{ messageForm.errors.request_message }}</p>
            <button :disabled="messageForm.processing" class="mt-2 rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white">Salvar</button>
        </form>
        <form v-if="responseOpen" class="mt-3 rounded-lg bg-slate-50 p-3" @submit.prevent="address">
            <label class="block text-sm font-medium text-slate-800">Resposta ao {{ request.requester_label }} <span class="font-normal text-slate-500">(opcional)</span><textarea v-model="responseForm.response_message" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
            <p v-if="responseForm.errors.response_message || responseForm.errors.request" class="mt-1 text-xs text-rose-700">{{ responseForm.errors.response_message || responseForm.errors.request }}</p>
            <button :disabled="responseForm.processing" class="mt-2 rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white">Marcar como atendida</button>
        </form>
        <form v-if="replacementOpen" class="mt-3" @submit.prevent="submitMessage(request.replace_url)">
            <label class="block text-sm font-medium text-slate-800">Nova mensagem para o {{ request.responder_label }}<textarea v-model="messageForm.request_message" required minlength="10" rows="3" class="mt-1 w-full rounded-lg border border-amber-300 px-3 py-2" /></label>
            <button :disabled="messageForm.processing" class="mt-2 rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white">Criar novo ajuste</button>
        </form>
        <form v-if="childOpen" class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3" @submit.prevent="createChild">
            <label class="block text-sm font-medium text-slate-800">Mensagem para o Inspetor<textarea v-model="childForm.request_message" required minlength="10" rows="3" class="mt-1 w-full rounded-lg border border-indigo-300 px-3 py-2" /></label>
            <label v-if="assessmentOptions.length" class="mt-3 block text-sm font-medium text-slate-800">Avaria <span class="font-normal text-slate-500">(opcional)</span><select v-model="childForm.defect_assessment_id" class="mt-1 w-full rounded-lg border border-indigo-300 px-3 py-2"><option value="">Usar o contexto atual</option><option v-for="option in assessmentOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></label>
            <p v-if="childForm.errors.request_message || childForm.errors.defect_assessment_id || childForm.errors.request" class="mt-1 text-xs text-rose-700">{{ childForm.errors.request_message || childForm.errors.defect_assessment_id || childForm.errors.request }}</p>
            <button :disabled="childForm.processing" class="mt-2 rounded-lg bg-indigo-700 px-3 py-2 text-sm font-semibold text-white">Encaminhar ao Inspetor</button>
        </form>

        <div v-if="request.children?.length" class="mt-4 space-y-3 border-t border-amber-100 pt-3">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ajustes encaminhados</p>
            <CorrectionRequestCard v-for="child in request.children" :key="child.public_id" :request="child" :assessment-options="assessmentOptions" nested />
        </div>
    </article>
</template>
