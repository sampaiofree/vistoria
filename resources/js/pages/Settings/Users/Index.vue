<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, default: () => [] },
    account_type_options: { type: Array, default: () => [] },
    create_url: { type: String, required: true },
});

const page = usePage();
const form = {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    account_type: props.filters.account_type ?? '',
};
const temporaryCredentials = computed(() => page.props.flash?.temporary_credentials ?? null);

function search() {
    router.get('/settings/users', form, { preserveState: true, replace: true });
}

function changeStatus(user) {
    const next = user.status === 'active' ? 'inactive' : 'active';
    if (!window.confirm(`${next === 'active' ? 'Reativar' : 'Inativar'} ${user.name}?`)) return;
    router.patch(user.status_url, { status: next }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Usuários" subtitle="Pessoas autorizadas a acessar a organização e participar das inspeções.">
        <template #actions>
            <Link :href="create_url" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">Novo usuário</Link>
        </template>

        <section v-if="temporaryCredentials" class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
            <p class="text-sm font-bold">Senha temporária criada</p>
            <p class="mt-1 text-sm">Entregue estes dados ao usuário. A senha será exibida somente agora e deverá ser trocada no primeiro acesso.</p>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><span class="block text-xs font-semibold uppercase tracking-wider text-amber-700">E-mail</span><code class="font-semibold">{{ temporaryCredentials.email }}</code></div>
                <div><span class="block text-xs font-semibold uppercase tracking-wider text-amber-700">Senha temporária</span><code class="font-semibold">{{ temporaryCredentials.password }}</code></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="grid gap-3 md:grid-cols-[1fr_180px_220px_auto]" @submit.prevent="search">
                <input v-model="form.search" type="search" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Buscar por nome ou e-mail">
                <select v-model="form.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">Todos os status</option><option v-for="option in status_options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select v-model="form.account_type" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">Todos os perfis</option><option v-for="option in account_type_options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <button class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Filtrar</button>
            </form>
        </section>

        <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100 md:hidden">
                <article v-for="user in users.data" :key="user.public_id" class="p-5">
                    <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold text-slate-950">{{ user.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ user.email }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="user.status === 'active' ? 'bg-emerald-100 text-emerald-800' : user.status === 'suspended' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'">{{ user.status_label }}</span></div>
                    <p class="mt-3 text-xs text-slate-500">{{ user.account_type_label }}<span v-if="user.open_inspections_count"> · {{ user.open_inspections_count }} inspeção(ões) aberta(s)</span></p>
                    <div class="mt-4 flex gap-2"><Link :href="user.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Editar</Link><button v-if="!user.is_current_user && user.status !== 'suspended'" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" @click="changeStatus(user)">{{ user.status === 'active' ? 'Inativar' : 'Reativar' }}</button></div>
                </article>
            </div>
            <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Usuário</th><th class="px-5 py-3">Perfil</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Inspeções abertas</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="user in users.data" :key="user.public_id" class="border-t border-slate-100"><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ user.name }}</p><p class="text-xs text-slate-500">{{ user.email }}</p></td><td class="px-5 py-4 text-slate-600">{{ user.account_type_label }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="user.status === 'active' ? 'bg-emerald-100 text-emerald-800' : user.status === 'suspended' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'">{{ user.status_label }}</span></td><td class="px-5 py-4 text-slate-600">{{ user.open_inspections_count }}</td><td class="px-5 py-4 text-right"><div class="flex justify-end gap-2"><Link :href="user.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Editar</Link><button v-if="!user.is_current_user && user.status !== 'suspended'" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" @click="changeStatus(user)">{{ user.status === 'active' ? 'Inativar' : 'Reativar' }}</button></div></td></tr><tr v-if="!users.data.length"><td colspan="5" class="px-5 py-10 text-center text-slate-500">Nenhum usuário encontrado.</td></tr></tbody></table></div>
            <div class="border-t border-slate-200 p-5"><Pagination :links="users.links" /></div>
        </section>
    </AppLayout>
</template>
