<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({
    action: { type: String, required: true },
    documents: { type: Array, default: () => [] },
    selectedDocumentIds: { type: Array, default: () => [] },
});
const form = useForm({ reference_document_ids: [] });

watch(
    () => props.selectedDocumentIds,
    (value) => {
        form.reference_document_ids = [...value];
    },
    { immediate: true },
);

function submit() { form.put(props.action, { preserveScroll: true }); }
</script>
<template>
    <form class="space-y-3" @submit.prevent="submit">
        <div v-if="documents.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
            Nenhum documento disponível para selecionar.
        </div>
        <label v-for="document in documents" :key="document.id" class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm">
            <input v-model="form.reference_document_ids" type="checkbox" :value="document.id" class="mt-1">
            <span>
                <strong class="block text-slate-800">{{ document.title }}</strong>
                <span class="block text-slate-500">Revisão {{ document.revision ?? '—' }} · {{ document.status_label }}</span>
                <span v-if="document.document_number" class="block text-slate-500">Doc. {{ document.document_number }}</span>
            </span>
        </label>
        <p v-if="form.errors.reference_document_ids" class="text-xs text-rose-600">{{ form.errors.reference_document_ids }}</p>
        <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Salvar referências</button>
    </form>
</template>
