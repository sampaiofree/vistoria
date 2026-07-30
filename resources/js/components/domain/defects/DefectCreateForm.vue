<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
});

const form = useForm({
    title: '',
    origin_description: '',
    location_description: '',
    comment: '',
    recommendation: '',
    internal_notes: '',
    assessment_action: 'draft',
});

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const labelClass = 'text-sm font-medium text-slate-700';
const helpClass = 'mt-1 text-xs text-rose-600';

function submit(action) {
    form.assessment_action = action;
    form.post(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit('draft')">
        <div class="rounded-xl border border-teal-100 bg-teal-50/70 p-4 text-sm text-teal-900">
            A avaria será criada agora e a primeira avaliação pode ficar como <strong>rascunho</strong> ou ser concluída no mesmo passo.
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block lg:col-span-2">
                <span :class="labelClass">Título</span>
                <input v-model="form.title" :class="inputClass" type="text" maxlength="200" autocomplete="off">
                <p v-if="form.errors.title" :class="helpClass">{{ form.errors.title }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Descrição da origem</span>
                <textarea v-model="form.origin_description" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.origin_description" :class="helpClass">{{ form.errors.origin_description }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Localização</span>
                <input v-model="form.location_description" :class="inputClass" type="text" maxlength="500" autocomplete="off">
                <p v-if="form.errors.location_description" :class="helpClass">{{ form.errors.location_description }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Comentário</span>
                <textarea v-model="form.comment" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.comment" :class="helpClass">{{ form.errors.comment }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Recomendação</span>
                <textarea v-model="form.recommendation" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.recommendation" :class="helpClass">{{ form.errors.recommendation }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Observações internas</span>
                <textarea v-model="form.internal_notes" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.internal_notes" :class="helpClass">{{ form.errors.internal_notes }}</p>
            </label>
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit('draft')"
            >
                Salvar rascunho
            </button>
            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit('complete')"
            >
                Concluir avaria
            </button>
        </div>
    </form>
</template>
