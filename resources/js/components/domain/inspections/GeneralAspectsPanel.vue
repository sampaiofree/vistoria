<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';
import GeneralAspectsEditor from '@/components/domain/inspections/GeneralAspectsEditor.vue';

const props = defineProps({
    aspects: { type: Object, default: () => ({}) },
});

const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const cloneDocument = (document) => JSON.parse(JSON.stringify(document || emptyDocument()));

const editing = ref(false);
const selectedTemplatePublicId = ref('');
const pendingTemplate = ref(null);
const templateSelect = ref(null);
const cancelTemplateConfirmationButton = ref(null);
let previousFocus = null;
const form = useForm({
    schema_version: props.aspects.schema_version ?? 1,
    document: cloneDocument(props.aspects.document),
});

const canEdit = computed(() => props.aspects.can_edit === true && Boolean(props.aspects.update_url));
const templates = computed(() => Array.isArray(props.aspects.templates) ? props.aspects.templates : []);

function syncForm() {
    form.defaults({
        schema_version: props.aspects.schema_version ?? 1,
        document: cloneDocument(props.aspects.document),
    });
    form.reset();
}

watch(() => props.aspects, syncForm, { deep: true });

function startEditing() {
    syncForm();
    selectedTemplatePublicId.value = '';
    pendingTemplate.value = null;
    editing.value = true;
}

function cancelEditing() {
    editing.value = false;
    selectedTemplatePublicId.value = '';
    pendingTemplate.value = null;
    form.reset();
    form.clearErrors();
}

function applySelectedTemplate() {
    const template = templates.value.find(({ public_id }) => public_id === selectedTemplatePublicId.value);
    if (!template) return;

    selectedTemplatePublicId.value = '';
    pendingTemplate.value = template;
}

function closeTemplateConfirmation() {
    pendingTemplate.value = null;
}

function confirmTemplateApplication() {
    if (!pendingTemplate.value) return;

    form.document = cloneDocument(pendingTemplate.value.document);
    closeTemplateConfirmation();
}

function onKeydown(event) {
    if (event.key === 'Escape') closeTemplateConfirmation();
}

watch(pendingTemplate, async (template) => {
    if (typeof document === 'undefined') return;

    if (template) {
        previousFocus = templateSelect.value ?? document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        cancelTemplateConfirmationButton.value?.focus();
        return;
    }

    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
    previousFocus?.focus?.();
    previousFocus = null;
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;

    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

function submit() {
    form.put(props.aspects.update_url, {
        preserveScroll: true,
        only: ['general_aspects', 'capabilities', 'flash'],
        onSuccess: () => {
            editing.value = false;
            selectedTemplatePublicId.value = '';
            pendingTemplate.value = null;
            form.clearErrors();
        },
    });
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Conteúdo do relatório</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Aspectos gerais do equipamento</h2>
                <p class="mt-1 text-sm text-slate-500">Este conteúdo será apresentado logo após a capa do relatório.</p>
            </div>
            <button
                v-if="canEdit && !editing"
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400"
                @click="startEditing"
            >
                Editar
            </button>
        </div>

        <form v-if="editing" class="mt-6 border-t border-slate-100 pt-5" @submit.prevent="submit">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Escolha um modelo para começar ou escreva o conteúdo manualmente.</p>
                <select
                    ref="templateSelect"
                    v-model="selectedTemplatePublicId"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!templates.length"
                    aria-label="Usar modelo de aspectos gerais"
                    @change="applySelectedTemplate"
                >
                    <option value="">Usar modelo…</option>
                    <option v-for="template in templates" :key="template.public_id" :value="template.public_id">
                        {{ template.name }}
                    </option>
                </select>
            </div>
            <GeneralAspectsEditor v-model="form.document" />
            <p class="mt-2 text-xs font-medium text-rose-700">Antes de salvar, substitua os trechos em vermelho e retorne-os à cor padrão.</p>
            <p class="mt-2 text-xs text-slate-500">Até 100.000 caracteres. O conteúdo será paginado automaticamente no formato A4.</p>
            <p v-if="form.errors.document" class="mt-2 text-sm text-rose-600">{{ form.errors.document }}</p>
            <div class="mt-5 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="cancelEditing">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50">Salvar aspectos gerais</button>
            </div>
        </form>

        <div v-else class="mt-6 border-t border-slate-100 pt-5">
            <div v-if="aspects.has_content" class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-700">
                <GeneralAspectsDocument :document="aspects.document" />
            </div>
            <p v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                Nenhum conteúdo informado. A seção não será incluída no relatório.
            </p>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="pendingTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="closeTemplateConfirmation">
            <section role="dialog" aria-modal="true" aria-labelledby="apply-general-aspects-template-title" aria-describedby="apply-general-aspects-template-description" class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex size-10 items-center justify-center rounded-full bg-amber-100 text-lg font-bold text-amber-700" aria-hidden="true">!</div>
                <p class="mt-4 text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Aspectos gerais</p>
                <h2 id="apply-general-aspects-template-title" class="mt-1 text-xl font-semibold text-slate-950">Usar modelo?</h2>
                <p class="mt-3 text-sm text-slate-600">
                    O modelo <strong class="text-slate-900">{{ pendingTemplate.name }}</strong> substituirá todo o conteúdo atual do editor.
                </p>
                <p id="apply-general-aspects-template-description" class="mt-2 text-sm text-slate-500">Você poderá editar o conteúdo antes de salvar os Aspectos Gerais.</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button ref="cancelTemplateConfirmationButton" type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeTemplateConfirmation">Cancelar</button>
                    <button type="button" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700" @click="confirmTemplateApplication">Usar modelo</button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
