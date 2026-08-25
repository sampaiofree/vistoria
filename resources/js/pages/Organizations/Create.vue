<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ action: String, cancel_url: String });
const form = useForm({
    name: '',
    legal_name: '',
    document: '',
    admin_name: '',
    admin_email: '',
});

function submit() {
    form.post(props.action, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Cadastrar empresa" subtitle="Crie uma organização e o primeiro acesso administrativo.">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">A empresa será criada ativa. Uma senha temporária será gerada para o administrador e exibida apenas após a conclusão do cadastro.</div>
            <form class="space-y-7" @submit.prevent="submit">
                <fieldset class="space-y-5"><legend class="text-base font-semibold text-slate-950">Dados da empresa</legend><label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p></label><label class="block"><span class="text-sm font-semibold text-slate-700">Razão social</span><input v-model="form.legal_name" type="text" maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.legal_name" class="mt-1 text-xs text-rose-600">{{ form.errors.legal_name }}</p></label><label class="block"><span class="text-sm font-semibold text-slate-700">CNPJ</span><input v-model="form.document" type="text" inputmode="numeric" maxlength="18" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.document" class="mt-1 text-xs text-rose-600">{{ form.errors.document }}</p></label></fieldset>
                <fieldset class="space-y-5 border-t border-slate-200 pt-6"><legend class="text-base font-semibold text-slate-950">Primeiro administrador</legend><label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.admin_name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.admin_name" class="mt-1 text-xs text-rose-600">{{ form.errors.admin_name }}</p></label><label class="block"><span class="text-sm font-semibold text-slate-700">E-mail *</span><input v-model="form.admin_email" type="email" maxlength="254" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.admin_email" class="mt-1 text-xs text-rose-600">{{ form.errors.admin_email }}</p></label></fieldset>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5"><Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Criar empresa</button></div>
            </form>
        </section>
    </AppLayout>
</template>
