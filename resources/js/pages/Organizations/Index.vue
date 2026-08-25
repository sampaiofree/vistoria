<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { formatDocument } from '@/lib/formatters';

const props = defineProps({
    organizations: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, default: () => [] },
    create_url: { type: String, required: true },
});

const page = usePage();
const temporaryCredentials = computed(() => page.props.flash?.temporary_credentials ?? null);
const form = {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
};

function search() {
    router.get('/admin/organizations', form, { preserveState: true, replace: true });
}

function changeStatus(organization) {
    const next = organization.can_suspend ? 'suspended' : 'active';
    const action = next === 'suspended' ? 'Suspender' : 'Reativar';

    if (!window.confirm(`${action} ${organization.name}?`)) return;

    router.patch(organization.status_url, { status: next }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Empresas" subtitle="Gerencie as organizações que utilizam a plataforma.">
        <template #actions>
            <Link :href="create_url" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">Cadastrar empresa</Link>
        </template>

        <section v-if="temporaryCredentials" class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
            <p class="text-sm font-bold">Administrador da empresa criado</p>
            <p class="mt-1 text-sm">Entregue estes dados ao administrador. A senha será exibida somente agora e deverá ser trocada no primeiro acesso.</p>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><span class="block text-xs font-semibold uppercase tracking-wider text-amber-700">E-mail</span><code class="font-semibold">{{ temporaryCredentials.email }}</code></div>
                <div><span class="block text-xs font-semibold uppercase tracking-wider text-amber-700">Senha temporária</span><code class="font-semibold">{{ temporaryCredentials.password }}</code></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="grid gap-3 md:grid-cols-[1fr_180px_auto]" @submit.prevent="search">
                <input v-model="form.search" type="search" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Buscar por nome, razão social ou CNPJ">
                <select v-model="form.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">Todos os status</option><option v-for="option in status_options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <button class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Filtrar</button>
            </form>
        </section>

        <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100 md:hidden">
                <article v-for="organization in organizations.data" :key="organization.public_id" class="p-5">
                    <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold text-slate-950">{{ organization.name }}</h2><p v-if="organization.legal_name" class="mt-1 text-sm text-slate-500">{{ organization.legal_name }}</p></div><StatusBadge :status="organization.status" /></div>
                    <p class="mt-3 text-sm text-slate-600">{{ formatDocument(organization.document) }}</p>
                    <p v-if="organization.administrator" class="mt-2 text-xs text-slate-500">Administrador inicial: {{ organization.administrator.name }} · {{ organization.administrator.email }}</p>
                    <div class="mt-4 flex flex-wrap gap-2"><Link :href="organization.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Editar</Link><button v-if="organization.can_suspend || organization.can_reactivate" type="button" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800" @click="changeStatus(organization)">{{ organization.can_suspend ? 'Suspender' : 'Reativar' }}</button></div>
                </article>
            </div>
            <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Empresa</th><th class="px-5 py-3">CNPJ</th><th class="px-5 py-3">Administrador inicial</th><th class="px-5 py-3">Usuários</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody><tr v-for="organization in organizations.data" :key="organization.public_id" class="border-t border-slate-100"><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ organization.name }}</p><p v-if="organization.legal_name" class="text-xs text-slate-500">{{ organization.legal_name }}</p></td><td class="px-5 py-4 text-slate-600">{{ formatDocument(organization.document) }}</td><td class="px-5 py-4"><template v-if="organization.administrator"><p class="font-medium text-slate-800">{{ organization.administrator.name }}</p><p class="text-xs text-slate-500">{{ organization.administrator.email }}</p></template><span v-else class="text-slate-500">Não informado</span></td><td class="px-5 py-4 text-slate-600">{{ organization.users_count }}</td><td class="px-5 py-4"><StatusBadge :status="organization.status" /></td><td class="px-5 py-4 text-right"><div class="flex justify-end gap-2"><Link :href="organization.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Editar</Link><button v-if="organization.can_suspend || organization.can_reactivate" type="button" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800" @click="changeStatus(organization)">{{ organization.can_suspend ? 'Suspender' : 'Reativar' }}</button></div></td></tr><tr v-if="!organizations.data.length"><td colspan="6" class="px-5 py-10 text-center text-slate-500">Nenhuma empresa encontrada.</td></tr></tbody></table></div>
            <div class="border-t border-slate-200 p-5"><Pagination :links="organizations.links" /></div>
        </section>
    </AppLayout>
</template>
