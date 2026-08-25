<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ organization: Object, action: String, cancel_url: String });
const form = useForm({
    name: props.organization.name ?? '',
    legal_name: props.organization.legal_name ?? '',
    document: props.organization.document ?? '',
});

function submit() {
    form.put(props.action, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Editar empresa" subtitle="Atualize os dados cadastrais da organização.">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="space-y-5" @submit.prevent="submit">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Nome *</span><input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Razão social</span><input v-model="form.legal_name" type="text" maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.legal_name" class="mt-1 text-xs text-rose-600">{{ form.errors.legal_name }}</p></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">CNPJ</span><input v-model="form.document" type="text" inputmode="numeric" maxlength="18" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><p v-if="form.errors.document" class="mt-1 text-xs text-rose-600">{{ form.errors.document }}</p></label>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5"><Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Salvar alterações</button></div>
            </form>
        </section>
    </AppLayout>
</template>
