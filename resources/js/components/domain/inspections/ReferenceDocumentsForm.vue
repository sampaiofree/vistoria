<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ action: { type: String, required: true }, documents: { type: Array, default: () => [] } });
const form = useForm({ document_ids: [] });
function submit() { form.put(props.action, { preserveScroll: true }); }
</script>
<template>
    <form class="space-y-3" @submit.prevent="submit">
        <label v-for="document in documents" :key="document.id" class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm">
            <input v-model="form.document_ids" type="checkbox" :value="document.id" class="mt-1">
            <span><strong class="block text-slate-800">{{ document.title }}</strong><span class="text-slate-500">Revisão {{ document.revision }}</span></span>
        </label>
        <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Salvar referências</button>
    </form>
</template>
