<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    inspection: { type: Object, required: true },
    metadata: { type: Object, default: () => ({}) },
    emissionOptions: { type: Array, default: () => [] },
    capability: { type: [Object, Boolean], default: false },
});

const editing = ref(false);
const textarea = ref(null);

const form = useForm({
    emission_type: props.metadata.emission_type ?? '',
    report_date: props.metadata.report_date ?? '',
    service_order: props.metadata.service_order ?? '',
    external_report_number: props.metadata.external_report_number ?? '',
    report_designer: props.metadata.report_designer ?? 'PROJETISTA II',
    designer_i_report_number: props.metadata.designer_i_report_number ?? '',
    first_page_text_template: props.metadata.first_page_text_template ?? '',
    confirm_revision_reorder: false,
});

const canEdit = computed(() => props.metadata.can_edit === true && Boolean(props.metadata.update_url));
const canEditRestrictedFields = computed(() => props.metadata.can_edit_restricted_fields === true);
const displayedEmission = computed(() => props.metadata.emission_type
    ? `${props.metadata.emission_type} — ${props.metadata.emission_type_label || ''}`
    : 'Não definido');
const resolvedText = computed(() => (props.metadata.first_page_text_template || '')
    .replaceAll('[nome do equipamento]', props.inspection.equipment?.name || ''));

function syncForm() {
    form.defaults({
        emission_type: props.metadata.emission_type ?? '',
        report_date: props.metadata.report_date ?? '',
        service_order: props.metadata.service_order ?? '',
        external_report_number: props.metadata.external_report_number ?? '',
        report_designer: props.metadata.report_designer ?? 'PROJETISTA II',
        designer_i_report_number: props.metadata.designer_i_report_number ?? '',
        first_page_text_template: props.metadata.first_page_text_template ?? '',
        confirm_revision_reorder: false,
    });
    form.reset();
}

watch(() => props.metadata, syncForm, { deep: true });

function startEditing() {
    syncForm();
    editing.value = true;
}

function cancelEditing() {
    editing.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    const dateChanged = (props.metadata.report_date || '') !== (form.report_date || '');
    if (props.inspection.status === 'released' && dateChanged) {
        if (! window.confirm('A alteração pode renumerar a cronologia do equipamento. Deseja continuar?')) {
            return;
        }
        form.confirm_revision_reorder = true;
    }

    form.put(props.metadata.update_url, {
        preserveScroll: true,
        only: ['inspection', 'report_metadata', 'emission_options', 'capabilities', 'transitions', 'flash'],
        onSuccess: () => {
            editing.value = false;
            form.clearErrors();
        },
    });
}

async function insertPlaceholder() {
    const token = '[nome do equipamento]';
    const start = textarea.value?.selectionStart ?? form.first_page_text_template.length;
    const end = textarea.value?.selectionEnd ?? start;
    form.first_page_text_template = `${form.first_page_text_template.slice(0, start)}${token}${form.first_page_text_template.slice(end)}`;
    await nextTick();
    textarea.value?.focus();
    textarea.value?.setSelectionRange(start + token.length, start + token.length);
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Configuração</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Dados do relatório</h2>
                <p class="mt-1 text-sm text-slate-500">Metadados administrativos da emissão da inspeção.</p>
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

        <form v-if="editing" class="mt-6 space-y-5 border-t border-slate-100 pt-5" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Tipo de emissão</span>
                    <select v-model="form.emission_type" :disabled="!canEditRestrictedFields" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100">
                        <option value="">Não definido</option>
                        <option v-for="option in emissionOptions" :key="option.value" :value="option.value">
                            {{ option.value }} — {{ option.label }}
                        </option>
                    </select>
                    <span v-if="form.errors.emission_type" class="block text-xs text-rose-600">{{ form.errors.emission_type }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Data do relatório</span>
                    <input v-model="form.report_date" :disabled="!canEditRestrictedFields" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100">
                    <span class="block text-xs font-normal text-slate-500">Preencha a data oficial do relatório quando aplicável.</span>
                    <span v-if="form.errors.report_date" class="block text-xs text-rose-600">{{ form.errors.report_date }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                    <span>O.S.</span>
                    <input v-model="form.service_order" :disabled="!canEditRestrictedFields" type="text" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100">
                    <span v-if="form.errors.service_order" class="block text-xs text-rose-600">{{ form.errors.service_order }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Número do relatório externo</span>
                    <input v-model="form.external_report_number" :disabled="!canEditRestrictedFields" type="text" maxlength="150" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100">
                    <span v-if="form.errors.external_report_number" class="block text-xs text-rose-600">{{ form.errors.external_report_number }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Projetista</span>
                    <input v-model="form.report_designer" required type="text" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    <span v-if="form.errors.report_designer" class="block text-xs text-rose-600">{{ form.errors.report_designer }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                    <span>Nº Projetista I</span>
                    <input v-model="form.designer_i_report_number" type="text" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="block text-xs font-normal text-slate-500">Obrigatório somente para impressão, PDF e DOC.</span>
                    <span v-if="form.errors.designer_i_report_number" class="block text-xs text-rose-600">{{ form.errors.designer_i_report_number }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                    <span>Título da primeira página</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-100" @click="insertPlaceholder">
                            Inserir nome do equipamento
                        </button>
                        <span class="self-center text-xs font-normal text-slate-500">Use [nome do equipamento] para inserir o nome atual.</span>
                    </div>
                    <textarea ref="textarea" v-model="form.first_page_text_template" rows="6" maxlength="5000" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></textarea>
                    <span v-if="form.errors.first_page_text_template" class="block text-xs text-rose-600">{{ form.errors.first_page_text_template }}</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="cancelEditing">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50">Salvar</button>
            </div>
        </form>

        <dl v-else class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">T.E.</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ displayedEmission }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Data do relatório</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ metadata.report_date ? new Date(`${metadata.report_date}T00:00:00`).toLocaleDateString('pt-BR') : 'Não definida' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">O.S.</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ metadata.service_order || 'Não informada' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Relatório externo</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ metadata.external_report_number || 'Não informado' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Projetista</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ metadata.report_designer || 'PROJETISTA II' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Nº Projetista I</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ metadata.designer_i_report_number || 'Não informado' }}</dd></div>
            <div class="sm:col-span-2 lg:col-span-4"><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Título da primeira página</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ resolvedText || 'Nenhum título personalizado.' }}</dd></div>
        </dl>
        <p v-if="metadata.updated_by" class="mt-5 text-xs text-slate-500">Última alteração por {{ metadata.updated_by.name }}{{ metadata.updated_at ? ` em ${metadata.updated_at}` : '' }}.</p>
    </section>
</template>
