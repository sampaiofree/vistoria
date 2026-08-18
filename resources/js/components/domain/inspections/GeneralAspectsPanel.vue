<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';
import GeneralAspectsEditor from '@/components/domain/inspections/GeneralAspectsEditor.vue';

const props = defineProps({
    aspects: { type: Object, default: () => ({}) },
});

const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const cloneDocument = (document) => JSON.parse(JSON.stringify(document || emptyDocument()));

const editing = ref(false);
const form = useForm({
    schema_version: props.aspects.schema_version ?? 1,
    document: cloneDocument(props.aspects.document),
});

const canEdit = computed(() => props.aspects.can_edit === true && Boolean(props.aspects.update_url));

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
    editing.value = true;
}

function cancelEditing() {
    editing.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.put(props.aspects.update_url, {
        preserveScroll: true,
        only: ['general_aspects', 'capabilities', 'flash'],
        onSuccess: () => {
            editing.value = false;
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
            <GeneralAspectsEditor v-model="form.document" />
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
</template>
