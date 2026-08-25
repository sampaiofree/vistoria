<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });
function submit() { form.put('/account/password'); }
</script>

<template>
    <AppLayout title="Alterar senha" subtitle="Mantenha o acesso da sua conta protegido.">
        <section class="max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">A senha deve ter pelo menos 6 caracteres, incluindo letras maiúsculas, minúsculas, números e caracteres especiais.</div>
            <form class="space-y-5" @submit.prevent="submit">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Senha atual</span><input v-model="form.current_password" type="password" autocomplete="current-password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.current_password" class="mt-1 text-xs text-rose-600">{{ form.errors.current_password }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Nova senha</span><input v-model="form.password" type="password" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Confirmar nova senha</span><input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></label>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5"><Link href="/dashboard" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Voltar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Atualizar senha</button></div>
            </form>
        </section>
    </AppLayout>
</template>
