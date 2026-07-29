<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AssignmentForm from '@/components/domain/inspections/AssignmentForm.vue';
import InspectionSnapshot from '@/components/domain/inspections/InspectionSnapshot.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTimeline from '@/components/domain/inspections/InspectionTimeline.vue';
import ReferenceDocumentsForm from '@/components/domain/inspections/ReferenceDocumentsForm.vue';
import TransitionForm from '@/components/domain/inspections/TransitionForm.vue';

defineProps({ inspection: { type: Object, required: true }, capabilities: { type: Object, required: true }, assignment_options: { type: Object, default: () => ({ users: [], roles: [] }) }, available_documents: { type: Array, default: () => [] }, transitions: { type: Array, default: () => [] }, index_url: { type: String, required: true } });
</script>
<template>
    <AppLayout :title="inspection.number" :subtitle="`${inspection.equipment.tag} — ${inspection.equipment.name}`">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><Link :href="index_url" class="text-sm font-semibold text-teal-700">← Voltar às inspeções</Link><InspectionStatusBadge :status="inspection.status" /></div>
        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4"><div><div class="text-xs uppercase text-slate-500">Tipo</div><div class="font-semibold">{{ inspection.type === 'reinspection' ? 'Reinspeção' : 'Inicial' }}</div></div><div><div class="text-xs uppercase text-slate-500">Planejada</div><div class="font-semibold">{{ inspection.scheduled_at || '—' }}</div></div><div><div class="text-xs uppercase text-slate-500">Inspeção anterior</div><Link v-if="inspection.previous_inspection" :href="inspection.previous_inspection.show_url" class="font-semibold text-teal-700">{{ inspection.previous_inspection.number }}</Link><div v-else>—</div></div><div><div class="text-xs uppercase text-slate-500">Estado atual</div><InspectionStatusBadge class="mt-1" :status="inspection.status" /></div></section>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 text-lg font-semibold">Snapshot da inspeção</h2><InspectionSnapshot :snapshot="inspection.context_snapshot" :version="inspection.snapshot_version" /></section>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 text-lg font-semibold">Responsáveis</h2><ul class="mb-5 divide-y divide-slate-100"><li v-for="item in inspection.responsibles" :key="`${item.user.id}-${item.responsibility}`" class="flex justify-between py-3 text-sm"><span>{{ item.user.name }}</span><span class="text-slate-500">{{ item.responsibility_label }}<strong v-if="item.is_primary"> · Principal</strong></span></li><li v-if="inspection.responsibles.length === 0" class="py-3 text-sm text-slate-500">Nenhum responsável atribuído.</li></ul><AssignmentForm v-if="capabilities.assign_responsibles" :action="capabilities.assign_responsibles.action" :users="assignment_options.users" :roles="assignment_options.roles" /></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 text-lg font-semibold">Documentos de referência</h2><ul class="mb-5 space-y-2"><li v-for="document in inspection.reference_documents" :key="document.id" class="rounded-lg bg-slate-50 p-3 text-sm"><strong>{{ document.title }}</strong> · Revisão {{ document.revision }}</li><li v-if="inspection.reference_documents.length === 0" class="text-sm text-slate-500">Nenhum documento selecionado.</li></ul><ReferenceDocumentsForm v-if="capabilities.manage_references" :action="capabilities.manage_references.action" :documents="available_documents" /></section>
        </div>
        <section v-if="capabilities.transition && transitions.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 text-lg font-semibold">Ações disponíveis</h2><div class="grid gap-4 md:grid-cols-2"><TransitionForm v-for="transition in transitions" :key="transition.key" :transition="transition" /></div></section>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-5 text-lg font-semibold">Histórico</h2><InspectionTimeline :history="inspection.history" /></section>
    </AppLayout>
</template>
