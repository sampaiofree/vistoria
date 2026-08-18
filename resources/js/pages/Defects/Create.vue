<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectCreateForm from '@/components/domain/defects/DefectCreateForm.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

defineProps({
    inspection: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    action: { type: String, required: true },
    cancel_url: { type: String, required: true },
});
</script>

<template>
    <AppLayout
        title="Adicionar avaria"
        :subtitle="`${inspection.number || 'Inspeção'} · ${inspection.equipment.tag} — ${inspection.equipment.name}`"
        wide
    >
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="cancel_url" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
                    ← Voltar para avarias
                </Link>
                <InspectionStatusBadge :status="inspection.status" />
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-6 border-b border-slate-100 pb-5">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Nova ocorrência</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Registrar avaria</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        Preencha os dados iniciais. Depois do cadastro, você poderá completar a avaliação e anexar as evidências.
                    </p>
                </div>

                <DefectCreateForm :action="action" :categories="categories" />
            </section>
        </div>
    </AppLayout>
</template>
