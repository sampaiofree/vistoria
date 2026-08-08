<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

defineProps({
    inspection: { type: Object, required: true },
    checklist: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Checklist da reinspeção" :subtitle="inspection.number">
        <div class="mb-5 flex items-center justify-between gap-3">
            <Link :href="inspection.show_url" class="text-sm font-semibold text-teal-700 hover:text-teal-800">← Voltar para a inspeção</Link>
            <div v-if="checklist.is_reinspection" class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">
                {{ checklist.completed }} de {{ checklist.total }} avaliadas
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div v-if="!checklist.is_reinspection" class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                Esta inspeção é inicial e não possui checklist de avarias anteriores.
            </div>

            <template v-else>
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 pb-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-teal-700">Inspeção anterior</p>
                        <Link :href="checklist.previous_inspection.show_url" class="mt-1 block font-semibold text-slate-900 hover:text-teal-700">{{ checklist.previous_inspection.number }}</Link>
                    </div>
                    <p class="text-sm text-slate-500">Avarias ativas precisam de uma avaliação completa na inspeção atual.</p>
                </div>

                <div v-if="checklist.items.length" class="divide-y divide-slate-200">
                    <article v-for="item in checklist.items" :key="item.id" class="flex flex-col gap-4 py-5 first:pt-0 last:pb-0 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <Link :href="item.defect_url" class="font-semibold text-teal-700 hover:text-teal-800">{{ item.defect_code }}</Link>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ item.title }}</h2>
                            <p v-if="item.previous_condition_label" class="mt-2 text-sm text-slate-600">Última condição: {{ item.previous_condition_label }}</p>
                            <p v-if="item.previous_comment" class="mt-1 text-sm leading-6 text-slate-500">{{ item.previous_comment }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-start gap-2 lg:items-end">
                            <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="item.resolved ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                {{ item.resolved ? 'Avaliada' : 'Pendente' }}
                            </span>
                            <Link v-if="item.assessment_url" :href="item.assessment_url" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-teal-500 hover:text-teal-700">Abrir avaliação</Link>
                            <span v-else class="text-xs text-slate-500">Crie a avaliação pela lista de avarias.</span>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">Nenhuma avaria ativa anterior exige acompanhamento.</div>
            </template>
        </section>
    </AppLayout>
</template>
