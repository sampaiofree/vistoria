<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

defineProps({
    templates: { type: Array, default: () => [] },
    create_url: { type: String, required: true },
});

function remove(template) {
    if (!window.confirm(`Excluir o modelo “${template.name}”? Esta ação não altera inspeções já preenchidas.`)) return;
    router.delete(template.delete_url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Aspectos gerais" subtitle="Modelos de texto reutilizáveis para o relatório de inspeção.">
        <template #actions>
            <Link :href="create_url" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">Novo modelo</Link>
        </template>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div v-if="templates.length" class="divide-y divide-slate-100">
                <article v-for="template in templates" :key="template.public_id" class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-950">{{ template.name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Atualizado em {{ template.updated_at }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Link :href="template.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Editar</Link>
                        <button type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700" @click="remove(template)">Excluir</button>
                    </div>
                </article>
            </div>
            <div v-else class="p-10 text-center">
                <h2 class="font-semibold text-slate-950">Nenhum modelo cadastrado</h2>
                <p class="mt-1 text-sm text-slate-500">Crie um modelo para agilizar o preenchimento dos aspectos gerais.</p>
            </div>
        </section>
    </AppLayout>
</template>
