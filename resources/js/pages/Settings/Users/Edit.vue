<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ user: Object, action: String, status_url: String, reset_password_url: String, cancel_url: String, account_type_options: Array });
const form = useForm({ _method: 'put', name: props.user.name, email: props.user.email, account_type: props.user.account_type });
function submit() { form.put(props.action); }
function resetPassword() { if (window.confirm('Gerar uma nova senha temporária para este usuário?')) router.post(props.reset_password_url); }
function changeStatus() { const next = props.user.status === 'active' ? 'inactive' : 'active'; if (window.confirm(`${next === 'active' ? 'Reativar' : 'Inativar'} este usuário?`)) router.patch(props.status_url, { status: next }); }
</script>

<template>
    <AppLayout title="Editar usuário" :subtitle="user.email">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-teal-700">Acesso</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ user.name }}</h2></div><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="user.status === 'active' ? 'bg-emerald-100 text-emerald-800' : user.status === 'suspended' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'">{{ user.status_label }}</span></div>
            <form class="space-y-5" @submit.prevent="submit">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">E-mail *</span><input v-model="form.email" type="email" maxlength="254" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Perfil *</span><select v-model="form.account_type" :disabled="user.is_current_user" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:bg-slate-100"><option v-for="option in account_type_options" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="form.errors.account_type" class="mt-1 text-xs text-rose-600">{{ form.errors.account_type }}</p></label>
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-5"><div class="flex gap-2"><button v-if="!user.is_current_user && user.status !== 'suspended'" type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="changeStatus">{{ user.status === 'active' ? 'Inativar' : 'Reativar' }}</button><button v-if="reset_password_url" type="button" class="rounded-xl border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-800" @click="resetPassword">Redefinir senha</button></div><div class="flex gap-3"><Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Voltar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Salvar</button></div></div>
                <p v-if="user.open_inspections_count" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Este usuário está atribuído a {{ user.open_inspections_count }} inspeção(ões) aberta(s). Substitua as responsabilidades antes de inativá-lo.</p>
            </form>
        </section>
    </AppLayout>
</template>
