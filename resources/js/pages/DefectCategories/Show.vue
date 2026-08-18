<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({ category: { type: Object, required: true }, can: { type: Object, default: () => ({}) }, edit_url: { type: String, required: true }, classification_create_url: { type: String, required: true }, gut_edit_url: { type: String, required: true } });
function changeStatus() { router.patch(props.category.status_url, { status: props.category.status === 'active' ? 'inactive' : 'active' }, { preserveScroll: true }); }
</script>

<template>
    <AppLayout :title="category.name" :subtitle="`Categoria ${category.code}`">
        <template #actions><div class="flex flex-wrap gap-2"><Link href="/defect-categories" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Voltar</Link><Link v-if="can.update" :href="edit_url" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Editar</Link><button v-if="can.change_status" type="button" class="rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white" @click="changeStatus">{{ category.status === 'active' ? 'Inativar' : 'Ativar' }}</button></div></template>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Código</p><p class="mt-1 font-mono text-xl font-semibold text-slate-900">{{ category.code }}</p></div><div class="flex flex-wrap gap-2"><span v-if="category.requires_location_map" class="rounded-full bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700">Mapa obrigatório</span><span class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="category.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ category.status === 'active' ? 'Ativa' : 'Inativa' }}</span></div></div><p class="mt-4 text-sm leading-6 text-slate-600">{{ category.description || 'Sem descrição.' }}</p></section>
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5"><div><h2 class="text-lg font-semibold text-slate-900">Classificações</h2><p class="mt-1 text-sm text-slate-500">Seleções disponíveis nas avaliações desta categoria.</p></div><Link v-if="can.create_classification" :href="classification_create_url" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">Nova classificação</Link></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Código</th><th class="px-5 py-3">Nome</th><th class="px-5 py-3">Cor</th><th class="px-5 py-3">Criticidade</th><th class="px-5 py-3">Status</th><th></th></tr></thead><tbody><tr v-for="classification in category.classifications" :key="classification.public_id" class="border-t border-slate-100"><td class="px-5 py-4 font-mono font-semibold text-slate-900">{{ classification.code }}</td><td class="px-5 py-4 text-slate-700">{{ classification.name }}</td><td class="px-5 py-4"><span v-if="classification.color" class="inline-flex items-center gap-2 font-mono text-xs font-semibold text-slate-700"><span class="h-5 w-5 rounded-full border border-black/10 shadow-sm" :style="{ backgroundColor: classification.color }"></span>{{ classification.color }}</span><span v-else class="text-xs font-medium text-slate-500">Não definida</span></td><td class="px-5 py-4 text-slate-600">{{ classification.severity_rank ?? '—' }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="classification.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ classification.status === 'active' ? 'Ativa' : 'Inativa' }}</span></td><td class="px-5 py-4 text-right"><Link :href="classification.edit_url" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Editar</Link></td></tr><tr v-if="!category.classifications.length"><td colspan="6" class="px-5 py-10 text-center text-slate-500">Nenhuma classificação cadastrada.</td></tr></tbody></table></div></section>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Classificação GUT</h2>
                    <p class="mt-1 text-sm text-slate-500">Notas e cores específicas desta categoria. O cálculo do produto será definido posteriormente.</p>
                </div>
                <Link v-if="can.update" :href="gut_edit_url" class="rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white">Configurar GUT</Link>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <div v-for="criterion in [{ key: 'gravity', label: 'Gravidade (G)' }, { key: 'urgency', label: 'Urgência (U)' }, { key: 'trend', label: 'Tendência (T)' }]" :key="criterion.key" class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">{{ criterion.label }}</h3>
                    <div v-if="category.gut[criterion.key]?.length" class="mt-3 flex flex-wrap gap-2">
                        <span v-for="option in category.gut[criterion.key]" :key="option.id" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm font-semibold text-slate-800">
                            <span class="h-4 w-4 rounded border border-black/10" :style="{ backgroundColor: option.color }"></span>{{ option.score }}
                        </span>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-500">Não configurado.</p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
