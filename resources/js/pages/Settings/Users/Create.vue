<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ action: String, cancel_url: String, account_type_options: Array });
const form = useForm({ name: '', email: '', account_type: 'member' });
function submit() { form.post(props.action); }
</script>

<template>
    <AppLayout title="Novo usuário" subtitle="Crie um acesso para alguém da organização.">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">Uma senha temporária será gerada após o cadastro e exibida uma única vez. O usuário deverá trocá-la no primeiro acesso.</div>
            <form class="space-y-5" @submit.prevent="submit">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">E-mail *</span><input v-model="form.email" type="email" maxlength="254" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Perfil *</span><select v-model="form.account_type" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option v-for="option in account_type_options" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="form.errors.account_type" class="mt-1 text-xs text-rose-600">{{ form.errors.account_type }}</p></label>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5"><Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Criar usuário</button></div>
            </form>
        </section>
    </AppLayout>
</template>
