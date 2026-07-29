<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

const props = defineProps({ inspections: { type: Object, required: true }, filters: { type: Object, required: true }, options: { type: Object, required: true }, capabilities: { type: Object, required: true }, create_url: { type: String, required: true } });
const form = useForm({ number: props.filters.number ?? '', equipment: props.filters.equipment ?? '', status: props.filters.status ?? '', type: props.filters.type ?? '', responsible: props.filters.responsible ?? '', from: props.filters.from ?? '', to: props.filters.to ?? '' });
function submit() { form.get('/inspections', { preserveState: true, preserveScroll: true, replace: true }); }
function clear() { form.reset(); submit(); }
</script>

<template>
    <AppLayout title="Inspeções" subtitle="Planejamento, execução e liberação de inspeções.">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="grid gap-3 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="submit">
                <label class="text-sm font-medium text-slate-700">Número<input v-model="form.number" placeholder="INS-2026-000001" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="text-sm font-medium text-slate-700">Equipamento<select v-model="form.equipment" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Todos</option><option v-for="item in options.equipment" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                <label class="text-sm font-medium text-slate-700">Estado<select v-model="form.status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Todos</option><option v-for="item in options.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                <label class="text-sm font-medium text-slate-700">Tipo<select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Todos</option><option value="initial">Inicial</option><option value="reinspection">Reinspeção</option></select></label>
                <label class="text-sm font-medium text-slate-700">Responsável<select v-model="form.responsible" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Todos</option><option v-for="item in options.responsibles" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                <label class="text-sm font-medium text-slate-700">De<input v-model="form.from" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="text-sm font-medium text-slate-700">Até<input v-model="form.to" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <div class="flex items-end gap-2"><button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Filtrar</button><button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="clear">Limpar</button></div>
            </form>
            <div v-if="capabilities.create" class="mt-4 flex justify-end"><Link :href="create_url" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Nova inspeção</Link></div>
        </section>
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Número</th><th class="px-5 py-3">Equipamento</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3">Responsáveis</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Planejada</th><th></th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="inspection in inspections.data" :key="inspection.public_id"><td class="px-5 py-4 font-semibold text-slate-900">{{ inspection.number }}</td><td class="px-5 py-4"><strong>{{ inspection.equipment.tag }}</strong><div class="text-slate-500">{{ inspection.equipment.name }}</div></td><td class="px-5 py-4">{{ inspection.type === 'reinspection' ? 'Reinspeção' : 'Inicial' }}</td><td class="px-5 py-4 text-slate-600">{{ inspection.responsibles?.map(item => item.name).join(', ') || '—' }}</td><td class="px-5 py-4"><InspectionStatusBadge :status="inspection.status" /></td><td class="px-5 py-4">{{ inspection.scheduled_at || '—' }}</td><td class="px-5 py-4 text-right"><Link :href="inspection.show_url" class="font-semibold text-teal-700">Ver detalhes</Link></td></tr><tr v-if="inspections.data.length === 0"><td colspan="7" class="px-5 py-10 text-center text-slate-500">Nenhuma inspeção encontrada.</td></tr></tbody></table></div>
            <div class="border-t border-slate-200 px-5 py-4"><Pagination :links="inspections.links" /></div>
        </section>
    </AppLayout>
</template>
