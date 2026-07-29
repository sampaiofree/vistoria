<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    submitLabel: {
        type: String,
        default: 'Salvar',
    },
    cancelUrl: {
        type: String,
        required: true,
    },
});

defineEmits(['submit']);

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const labelClass = 'text-sm font-medium text-slate-700';
const helpClass = 'mt-1 text-xs text-rose-600';
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block lg:col-span-2">
                <span :class="labelClass">Nome</span>
                <input v-model="form.name" :class="inputClass" type="text" maxlength="150" autocomplete="off">
                <p v-if="form.errors.name" :class="helpClass">{{ form.errors.name }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Razao social</span>
                <input v-model="form.legal_name" :class="inputClass" type="text" maxlength="200" autocomplete="off">
                <p v-if="form.errors.legal_name" :class="helpClass">{{ form.errors.legal_name }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Documento</span>
                <input v-model="form.document" :class="inputClass" type="text" maxlength="20" inputmode="numeric" autocomplete="off">
                <p v-if="form.errors.document" :class="helpClass">{{ form.errors.document }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">E-mail</span>
                <input v-model="form.email" :class="inputClass" type="email" maxlength="254" autocomplete="off">
                <p v-if="form.errors.email" :class="helpClass">{{ form.errors.email }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Telefone</span>
                <input v-model="form.phone" :class="inputClass" type="text" maxlength="30" autocomplete="off">
                <p v-if="form.errors.phone" :class="helpClass">{{ form.errors.phone }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Observacoes</span>
                <textarea v-model="form.notes" :class="inputClass" rows="5" maxlength="5000"></textarea>
                <p v-if="form.errors.notes" :class="helpClass">{{ form.errors.notes }}</p>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Link
                :href="cancelUrl"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
            >
                Cancelar
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
