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
                <span :class="labelClass">Codigo</span>
                <input v-model="form.code" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.code" :class="helpClass">{{ form.errors.code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Timezone</span>
                <input v-model="form.timezone" :class="inputClass" type="text" maxlength="64" autocomplete="off">
                <p v-if="form.errors.timezone" :class="helpClass">{{ form.errors.timezone }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Codigo do pais</span>
                <input v-model="form.country_code" :class="inputClass" type="text" maxlength="2" autocomplete="off">
                <p v-if="form.errors.country_code" :class="helpClass">{{ form.errors.country_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">CEP</span>
                <input v-model="form.postal_code" :class="inputClass" type="text" maxlength="20" autocomplete="off">
                <p v-if="form.errors.postal_code" :class="helpClass">{{ form.errors.postal_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Estado</span>
                <input v-model="form.state" :class="inputClass" type="text" maxlength="100" autocomplete="off">
                <p v-if="form.errors.state" :class="helpClass">{{ form.errors.state }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Endereco</span>
                <input v-model="form.address_line" :class="inputClass" type="text" maxlength="200" autocomplete="off">
                <p v-if="form.errors.address_line" :class="helpClass">{{ form.errors.address_line }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Numero</span>
                <input v-model="form.address_number" :class="inputClass" type="text" maxlength="30" autocomplete="off">
                <p v-if="form.errors.address_number" :class="helpClass">{{ form.errors.address_number }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Bairro</span>
                <input v-model="form.district" :class="inputClass" type="text" maxlength="100" autocomplete="off">
                <p v-if="form.errors.district" :class="helpClass">{{ form.errors.district }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Cidade</span>
                <input v-model="form.city" :class="inputClass" type="text" maxlength="100" autocomplete="off">
                <p v-if="form.errors.city" :class="helpClass">{{ form.errors.city }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Observacoes</span>
                <textarea v-model="form.notes" :class="inputClass" rows="4" maxlength="5000"></textarea>
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
