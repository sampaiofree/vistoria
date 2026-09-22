<script setup>
import { Link } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
defineProps({ entries: { type: Array, default: () => [] } });
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Rastreabilidade</p><h2 class="mt-1 text-lg font-semibold text-slate-950">Histórico do equipamento</h2><p class="mt-1 text-sm text-slate-500">Inspeções e revisões persistidas do equipamento.</p></div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ entries.length }} registro(s)</span>
        </div>
        <div v-if="entries.length" class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 sm:px-6">Revisão</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Data</th><th class="px-3 py-3">Preparador</th><th class="px-3 py-3">Verificador</th><th class="px-3 py-3">Aprovador</th><th class="px-3 py-3">Liberador</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="entry in entries" :key="entry.key" :class="entry.is_current ? 'bg-teal-50/60' : 'bg-white'"><td class="px-5 py-3 align-top sm:px-6"><span class="font-semibold text-slate-950">{{ entry.revision_number ?? '—' }}</span><span v-if="entry.is_current" class="ml-2 rounded-full bg-teal-600 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white">Atual</span><p class="mt-0.5 text-xs text-slate-500">{{ entry.description }}</p><Link :href="entry.show_url" class="mt-0.5 inline-block text-xs font-semibold text-teal-700">{{ entry.inspection_number }}</Link></td><td class="px-3 py-3 align-top"><span v-if="entry.emission_type" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">T.E. {{ entry.emission_type }}</span><span v-else>—</span></td><td class="px-3 py-3 align-top"><InspectionStatusBadge :status="entry.status" /></td><td class="px-3 py-3 align-top whitespace-nowrap"><p>{{ entry.date ?? '—' }}</p><p v-if="entry.date_is_provisional" class="text-xs text-amber-700">Provisória</p></td><td v-for="role in ['preparer', 'reviewer', 'approver', 'releaser']" :key="role" class="max-w-40 px-3 py-3 align-top"><span class="block truncate" :title="entry.responsibles?.[role] ?? '—'">{{ entry.responsibles?.[role] ?? '—' }}</span></td></tr></tbody></table></div>
        <div v-else class="p-6 text-center text-sm text-slate-500">Nenhuma inspeção cadastrada.</div>
    </section>
</template>
