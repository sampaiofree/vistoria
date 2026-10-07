<script setup>
import ReportClassificationBadge from '@/components/domain/view-first/ReportClassificationBadge.vue';

defineProps({
    technical: { type: Object, default: () => ({}) },
    classificationCode: { type: String, default: '' },
    classificationColor: { type: String, default: null },
});
</script>

<template>
    <div class="space-y-3 text-sm">
        <div v-if="technical?.engineering_note_without_quantity" class="rounded-xl border border-violet-200 bg-violet-50 p-3 font-semibold text-violet-800">Nota de Engenharia · sem classificação GUT</div>
        <details v-else-if="technical?.classification" class="group rounded-xl border border-slate-200 bg-white">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-3 font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 [&::-webkit-details-marker]:hidden">
                <span>Classificação <ReportClassificationBadge :code="classificationCode" :color="classificationColor" /></span>
                <span aria-hidden="true" class="text-slate-500 group-open:rotate-180">⌄</span>
            </summary>
            <div class="border-t border-slate-200 p-3">
                <p class="font-semibold text-slate-900">Resultado {{ technical.classification.kind === 'tel' ? 'TEL' : 'GUT' }}: {{ technical.classification.score ?? 'Não informado' }}</p>
                <p v-if="technical.classification.kind === 'tel' && technical.classification.height_m" class="mt-1 text-slate-600">Altura registrada: {{ technical.classification.height_m }} m</p>
                <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div v-for="criterion in technical.classification.criteria ?? []" :key="criterion.key" class="min-w-0 rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs font-semibold text-slate-600">{{ criterion.label }}</dt>
                        <dd class="mt-1">
                            <span
                                v-if="technical.classification.kind === 'gut' && criterion.score !== null && criterion.score !== undefined"
                                class="inline-flex min-w-10 items-center justify-center rounded-lg px-3 py-2 text-base font-bold"
                                :class="criterion.color ? '' : 'bg-slate-100 text-slate-700'"
                                :style="criterion.color ? { backgroundColor: criterion.color, color: '#111827' } : {}"
                                :aria-label="`${criterion.label}: nota ${criterion.score}`"
                            >{{ criterion.score }}</span>
                            <span v-else class="font-bold text-slate-900">Nota {{ criterion.score ?? '—' }}</span>
                        </dd>
                        <p v-for="(detail, index) in criterion.details ?? []" :key="index" class="mt-1 break-words text-xs leading-5 text-slate-600">{{ detail }}</p>
                    </div>
                </dl>
                <p v-if="!(technical.classification.criteria ?? []).some((criterion) => criterion.details?.length)" class="mt-3 text-xs text-slate-500">Critérios descritivos indisponíveis nesta avaliação.</p>
            </div>
        </details>
        <div v-else class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="font-semibold text-slate-900">Classificação <ReportClassificationBadge :code="classificationCode" :color="classificationColor" /></p>
            <p class="mt-1 text-xs text-slate-500">Sem notas de classificação detalhadas nesta avaliação.</p>
        </div>

        <details class="group rounded-xl border border-slate-200 bg-white">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-3 font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 [&::-webkit-details-marker]:hidden">
                <span>Quantitativo <span class="text-slate-500">· {{ technical?.quantities?.length ?? 0 }} item(ns)</span></span>
                <span aria-hidden="true" class="text-slate-500 group-open:rotate-180">⌄</span>
            </summary>
            <div class="border-t border-slate-200 p-3">
                <p v-if="!technical?.quantities?.length" class="text-slate-500">Nenhum item de quantitativo informado.</p>
                <ol v-else class="space-y-2">
                    <li v-for="(item, index) in technical.quantities" :key="`${item.position}-${index}`" class="flex flex-wrap justify-between gap-x-4 gap-y-1 rounded-lg bg-slate-50 p-3">
                        <span class="min-w-0 text-slate-700">{{ item.description || `Item ${item.position ?? index + 1}` }}</span>
                        <strong class="shrink-0 text-slate-900">{{ item.total_label || 'Valor indisponível' }}</strong>
                    </li>
                </ol>
                <div v-if="technical?.quantity_totals?.length" class="mt-3 border-t border-slate-200 pt-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Totais por unidade</p>
                    <p v-for="(total, index) in technical.quantity_totals" :key="index" class="mt-1 font-semibold text-slate-900">{{ total }}</p>
                </div>
            </div>
        </details>
    </div>
</template>
