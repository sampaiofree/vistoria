<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: { type: String, required: true },
});

const form = useForm({
    relation_type: 'recurrence',
    title: '',
    origin_description: '',
    location_description: '',
    comment: '',
    recommendation: '',
});

function submit() {
    form.post(props.action, { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Criar ocorrência relacionada</h3>
        <p class="mt-1 text-sm text-slate-500">Use recorrência para um novo dano após reparo ou divisão para detalhar a ocorrência.</p>
        <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <label class="block"><span class="text-sm font-medium text-slate-700">Relação</span><select v-model="form.relation_type" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="recurrence">Recorrência</option><option value="split">Divisão</option><option value="related">Relacionada</option></select><p v-if="form.errors.relation_type" class="mt-1 text-xs text-rose-600">{{ form.errors.relation_type }}</p></label>
            <label class="block"><span class="text-sm font-medium text-slate-700">Título</span><input v-model="form.title" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" maxlength="200"><p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p></label>
            <label class="block sm:col-span-2"><span class="text-sm font-medium text-slate-700">Localização</span><input v-model="form.location_description" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" maxlength="500"></label>
            <label class="block sm:col-span-2"><span class="text-sm font-medium text-slate-700">Observação</span><textarea v-model="form.comment" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" rows="3"></textarea></label>
            <div class="sm:col-span-2"><button type="submit" :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-60">Criar nova avaria</button></div>
        </form>
    </section>
</template>
