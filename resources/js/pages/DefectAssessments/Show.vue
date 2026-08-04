<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectAssessmentStatusBadge from '@/components/domain/defects/DefectAssessmentStatusBadge.vue';
import DefectConditionBadge from '@/components/domain/defects/DefectConditionBadge.vue';
import CivilClassificationBadge from '@/components/domain/view-first/CivilClassificationBadge.vue';
import GutScoreCard from '@/components/domain/view-first/GutScoreCard.vue';
import PhotoGallery from '@/components/domain/view-first/PhotoGallery.vue';
import ProvisionalDataNotice from '@/components/domain/view-first/ProvisionalDataNotice.vue';

const props = defineProps({
    assessment: { type: Object, required: true },
    previous_assessment: { type: Object, default: null },
    classification: { type: Object, default: () => ({}) },
    gut: { type: Object, default: null },
    characterization: { type: Array, default: () => [] },
    quantities: { type: Array, default: () => [] },
    evidence: { type: Array, default: () => [] },
    assessment_navigation: { type: Object, required: true },
    condition_options: { type: Array, default: () => [] },
    capabilities: { type: Object, default: () => ({}) },
    demo: { type: Object, default: () => ({}) },
});

const form = useForm({
    condition: props.assessment.condition,
    location_description: props.assessment.location_description ?? '',
    comment: props.assessment.comment ?? '',
    recommendation: props.assessment.recommendation ?? '',
    reason: props.assessment.reason ?? '',
    internal_notes: props.assessment.internal_notes ?? '',
});

const requiresReason = computed(() => ['not_located', 'not_inspected'].includes(form.condition));
const isComplete = computed(() => props.assessment.status === 'complete');
const title = computed(() => props.assessment.defect?.code ?? 'Avaliação CIVIL');
const subtitle = computed(() => `${props.assessment.defect?.equipment?.tag ?? ''} — ${props.assessment.defect?.title ?? ''}`);

const inputClass = 'mt-1.5 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100';
const labelClass = 'text-sm font-semibold text-slate-700';
const errorClass = 'mt-1.5 text-xs font-medium text-rose-600';

function saveDraft() {
    if (!props.capabilities.update_url) {
        return;
    }

    form.patch(props.capabilities.update_url, {
        preserveScroll: true,
    });
}

function completeAssessment() {
    if (!props.capabilities.complete_url) {
        return;
    }

    form.post(props.capabilities.complete_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="title" :subtitle="subtitle" wide>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <Link :href="assessment_navigation.defects_url" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
                ← Voltar às avarias
            </Link>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <span>Avaria {{ assessment_navigation.position }} de {{ assessment_navigation.total }}</span>
                <DefectAssessmentStatusBadge :status="assessment.status" />
            </div>
        </div>

        <section class="relative overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-950/10 sm:p-8">
            <div class="pointer-events-none absolute -right-20 -top-28 h-72 w-72 rounded-full border-[52px] border-teal-400/10"></div>
            <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <DefectConditionBadge :condition="assessment.condition" />
                        <CivilClassificationBadge :code="classification.code" :label="classification.label" :historical="classification.historical" />
                        <span class="rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-xs font-semibold text-slate-300">{{ assessment.defect?.category_label }}</span>
                    </div>
                    <p class="mt-5 text-xs font-bold uppercase tracking-[0.18em] text-teal-300">{{ assessment.defect?.code }}</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ assessment.defect?.title }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-400">{{ assessment.location_description || 'Localização ainda não informada.' }}</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        v-if="assessment_navigation.previous_url"
                        :href="assessment_navigation.previous_url"
                        class="inline-flex min-h-11 items-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ← Anterior
                    </Link>
                    <Link
                        v-if="assessment_navigation.next_url"
                        :href="assessment_navigation.next_url"
                        class="inline-flex min-h-11 items-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Próxima →
                    </Link>
                </div>
            </div>
        </section>

        <ProvisionalDataNotice v-if="demo.enabled" class="mt-5" :message="demo.provisional_notice" />

        <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1.55fr)_minmax(21rem,0.65fr)]">
            <main class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">01 · Identificação</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-950">Condição observada</h2>
                            <p class="mt-1 text-sm text-slate-500">Estes campos fazem parte do registro real da avaliação.</p>
                        </div>
                        <DefectAssessmentStatusBadge :status="assessment.status" />
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span :class="labelClass">Condição atual</span>
                            <select v-model="form.condition" :class="inputClass" :disabled="!capabilities.update">
                                <option v-for="option in condition_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.condition" :class="errorClass">{{ form.errors.condition }}</p>
                        </label>
                        <label class="block">
                            <span :class="labelClass">Localização</span>
                            <input v-model="form.location_description" type="text" maxlength="500" :class="inputClass" :disabled="!capabilities.update">
                            <p v-if="form.errors.location_description" :class="errorClass">{{ form.errors.location_description }}</p>
                        </label>
                        <label v-if="requiresReason" class="block md:col-span-2">
                            <span :class="labelClass">Justificativa obrigatória</span>
                            <textarea v-model="form.reason" rows="3" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                            <p v-if="form.errors.reason" :class="errorClass">{{ form.errors.reason }}</p>
                        </label>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(18rem,0.7fr)_minmax(0,1.3fr)]">
                    <GutScoreCard :gut="gut" :classification="classification" />
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">02 · Caracterização</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Leitura técnica</h2>
                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div v-for="item in characterization" :key="item.label" class="rounded-2xl bg-slate-50 p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ item.label }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</dd>
                            </div>
                        </dl>
                    </article>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">03 · Quantitativos</p>
                    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-950">Dimensão da manifestação</h2>
                            <p class="mt-1 text-sm text-slate-500">Valores demonstrativos, preparados para futura persistência própria.</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">Somente leitura</span>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="quantity in quantities" :key="`${quantity.label}-${quantity.unit}`" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ quantity.label }}</p>
                            <p class="mt-2 text-xl font-semibold text-slate-950">{{ quantity.value }} <span class="text-sm font-medium text-slate-500">{{ quantity.unit }}</span></p>
                        </div>
                        <div v-if="quantities.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">Não aplicável para esta condição.</div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">04 · Registro técnico</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Comentário e recomendação</h2>
                    <div class="mt-6 space-y-4">
                        <label class="block">
                            <span :class="labelClass">Comentário técnico</span>
                            <textarea v-model="form.comment" rows="5" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                            <p class="mt-1.5 text-xs text-slate-500">Obrigatório para concluir a avaliação.</p>
                            <p v-if="form.errors.comment" :class="errorClass">{{ form.errors.comment }}</p>
                        </label>
                        <label class="block">
                            <span :class="labelClass">Recomendação</span>
                            <textarea v-model="form.recommendation" rows="5" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                            <p v-if="form.errors.recommendation" :class="errorClass">{{ form.errors.recommendation }}</p>
                        </label>
                        <details class="group rounded-2xl border border-slate-200 bg-slate-50">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 text-sm font-semibold text-slate-700">
                                Observações internas
                                <span class="text-lg text-slate-400 transition group-open:rotate-45">+</span>
                            </summary>
                            <div class="border-t border-slate-200 p-4">
                                <textarea v-model="form.internal_notes" rows="3" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                                <p v-if="form.errors.internal_notes" :class="errorClass">{{ form.errors.internal_notes }}</p>
                            </div>
                        </details>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">05 · Evidências</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-950">Registro fotográfico</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ evidence.length }} item(ns)</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ demo.photo_notice }}</p>
                    <div class="mt-5">
                        <PhotoGallery :photos="evidence" />
                    </div>
                </section>

                <div v-if="capabilities.update || capabilities.complete" class="sticky bottom-3 z-20 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl shadow-slate-950/10 backdrop-blur sm:p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="hidden text-xs leading-5 text-slate-500 sm:block">
                            GUT, CV, quantitativos e evidências não são alterados por estas ações.
                        </p>
                        <div class="flex gap-2 sm:flex-row">
                            <button
                                v-if="capabilities.update"
                                type="button"
                                :disabled="form.processing"
                                class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:cursor-not-allowed disabled:opacity-60 sm:flex-none sm:px-4"
                                @click="saveDraft"
                            >
                                {{ isComplete ? 'Reabrir como rascunho' : 'Salvar rascunho' }}
                            </button>
                            <button
                                v-if="capabilities.complete && !isComplete"
                                type="button"
                                :disabled="form.processing"
                                class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-teal-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:cursor-not-allowed disabled:opacity-60 sm:flex-none sm:px-5"
                                @click="completeAssessment"
                            >
                                Concluir avaliação
                            </button>
                        </div>
                    </div>
                </div>
            </main>

            <aside class="space-y-6 2xl:sticky 2xl:top-6 2xl:self-start">
                <section v-if="previous_assessment" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Avaliação anterior</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <DefectConditionBadge :condition="previous_assessment.condition" />
                        <span class="text-xs font-medium text-slate-500">{{ previous_assessment.inspection?.number }}</span>
                    </div>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div><dt class="font-semibold text-slate-500">Localização</dt><dd class="mt-1 leading-6 text-slate-800">{{ previous_assessment.location_description || '—' }}</dd></div>
                        <div><dt class="font-semibold text-slate-500">Comentário</dt><dd class="mt-1 leading-6 text-slate-800">{{ previous_assessment.comment || '—' }}</dd></div>
                        <div><dt class="font-semibold text-slate-500">Recomendação</dt><dd class="mt-1 leading-6 text-slate-800">{{ previous_assessment.recommendation || '—' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Navegação</p>
                    <h2 class="mt-2 font-semibold text-slate-950">Continue pela inspeção</h2>
                    <div class="mt-4 grid gap-2">
                        <Link v-if="assessment_navigation.next_url" :href="assessment_navigation.next_url" class="rounded-xl bg-slate-950 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-teal-700">Próxima avaria →</Link>
                        <Link v-if="assessment_navigation.previous_url" :href="assessment_navigation.previous_url" class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">← Avaria anterior</Link>
                        <Link :href="assessment_navigation.inspection_url" class="rounded-xl px-4 py-3 text-center text-sm font-semibold text-teal-700 hover:bg-teal-50">Visão geral da inspeção</Link>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
