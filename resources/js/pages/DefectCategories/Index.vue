<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';

defineProps({ categories: { type: Object, required: true }, can: { type: Object, default: () => ({}) }, create_url: { type: String, required: true } });
</script>

<template>
    <AppLayout title="Categorias de avarias" subtitle="Catálogo configurável da organização.">
        <template #actions>
            <Link v-if="can.create" :href="create_url" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">Nova categoria</Link>
        </template>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Categoria</th><th class="px-5 py-3">Código</th><th class="px-5 py-3">Classificações</th><th class="px-5 py-3">Localização</th><th class="px-5 py-3">Status</th><th></th></tr></thead>
                    <tbody><tr v-for="category in categories.data" :key="category.public_id" class="border-t border-slate-100"><td class="px-5 py-4 font-semibold text-slate-900">{{ category.name }}</td><td class="px-5 py-4 font-mono text-slate-600">{{ category.code }}</td><td class="px-5 py-4 text-slate-600">{{ category.classifications_count }}</td><td class="px-5 py-4 text-slate-600">{{ category.requires_location_map ? 'Mapa obrigatório' : 'Opcional' }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="category.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ category.status === 'active' ? 'Ativa' : 'Inativa' }}</span></td><td class="px-5 py-4 text-right"><Link :href="category.show_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Abrir</Link></td></tr><tr v-if="!categories.data.length"><td colspan="6" class="px-5 py-10 text-center text-slate-500">Nenhuma categoria cadastrada.</td></tr></tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-5"><Pagination :links="categories.links" /></div>
        </section>
    </AppLayout>
</template>
