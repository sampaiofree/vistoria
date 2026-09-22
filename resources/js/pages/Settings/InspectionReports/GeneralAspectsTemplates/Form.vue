<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import GeneralAspectsEditor from '@/components/domain/inspections/GeneralAspectsEditor.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    template: { type: Object, default: null },
    action: { type: String, required: true },
    method: { type: String, required: true },
    cancel_url: { type: String, required: true },
});

const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const cloneDocument = (document) => JSON.parse(JSON.stringify(document || emptyDocument()));
const form = useForm({
    name: props.template?.name ?? '',
    schema_version: props.template?.schema_version ?? 1,
    document: cloneDocument(props.template?.document),
});

function submit() {
    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="title" :subtitle="subtitle">
        <section class="max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <label class="block max-w-2xl">
                    <span class="text-sm font-semibold text-slate-700">Nome do modelo *</span>
                    <input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Ex.: Equipamento em boas condições">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </label>

                <div>
                    <span class="text-sm font-semibold text-slate-700">Conteúdo *</span>
                    <div class="mt-1.5"><GeneralAspectsEditor v-model="form.document" /></div>
                    <p class="mt-2 text-xs font-medium text-rose-700">Use a cor vermelha para indicar os trechos que o inspetor deverá preencher antes de salvar.</p>
                    <p class="mt-2 text-xs text-slate-500">Até 100.000 caracteres. A formatação será preservada ao usar o modelo.</p>
                    <p v-if="form.errors.document" class="mt-1 text-xs text-rose-600">{{ form.errors.document }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar modelo' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
