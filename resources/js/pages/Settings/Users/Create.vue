<script setup>
import { watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ action: String, cancel_url: String, account_type_options: Array, operational_role_options: Array, initial_account_type: String });
const form = useForm({ name: '', email: '', account_type: props.initial_account_type ?? 'member', operational_roles: [] });
watch(() => form.account_type, value => { if (value === 'client') form.operational_roles = []; });
function submit() { form.post(props.action); }
</script>

<template>
    <AppLayout title="Novo usuário" subtitle="Crie um acesso para alguém da organização.">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">Uma senha temporária será gerada após o cadastro e exibida uma única vez. O usuário deverá trocá-la no primeiro acesso.</div>
            <form class="space-y-5" @submit.prevent="submit">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">E-mail *</span><input v-model="form.email" type="email" maxlength="254" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Tipo de acesso *</span><select v-model="form.account_type" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option v-for="option in account_type_options" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="form.errors.account_type" class="mt-1 text-xs text-rose-600">{{ form.errors.account_type }}</p></label>
                <div v-if="form.account_type !== 'client'"><p class="text-sm font-semibold text-slate-700">Funções operacionais *</p><div class="mt-2 grid gap-2 sm:grid-cols-2"><label v-for="option in operational_role_options" :key="option.value" class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.operational_roles" type="checkbox" :value="option.value" class="rounded border-slate-300">{{ option.label }}</label></div><p v-if="form.errors.operational_roles" class="mt-1 text-xs text-rose-600">{{ form.errors.operational_roles }}</p></div>
                <p v-if="form.account_type === 'client'" class="text-sm text-slate-500">O acesso ao portal do cliente ainda não está disponível.</p>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5"><Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Criar usuário</button></div>
            </form>
        </section>
    </AppLayout>
</template>
