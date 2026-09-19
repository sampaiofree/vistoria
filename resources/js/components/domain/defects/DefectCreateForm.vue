<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    category: props.categories[0]?.code ?? '',
    title: '',
    assessment_action: 'draft',
});

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const labelClass = 'text-sm font-medium text-slate-700';
const helpClass = 'mt-1 text-xs text-rose-600';

function submit() {
    form.post(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="rounded-xl border border-teal-100 bg-teal-50/70 p-4 text-sm text-teal-900">
            Informe a categoria e o título para criar a avaria. Os demais dados serão preenchidos depois, na avaliação.
        </div>

        <div v-if="form.errors.defect_code_prefix" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ form.errors.defect_code_prefix }}
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <label v-if="categories.length" class="block lg:col-span-2">
                <span :class="labelClass">Categoria da avaria</span>
                <select v-model="form.category" :class="inputClass">
                    <option v-for="category in categories" :key="category.code" :value="category.code">{{ category.name }} ({{ category.code }})</option>
                </select>
                <p v-if="form.errors.category" :class="helpClass">{{ form.errors.category }}</p>
            </label>
            <label class="block lg:col-span-2">
                <span class="flex flex-wrap items-center gap-2" :class="labelClass">
                    Título
                    <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-teal-700">Vai para o relatório</span>
                </span>
                <input v-model="form.title" :class="inputClass" type="text" maxlength="200" autocomplete="off">
                <p v-if="form.errors.title" :class="helpClass">{{ form.errors.title }}</p>
            </label>

        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Salvar rascunho
            </button>
        </div>
    </form>
</template>
