<script setup>
import { Link } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    indexUrl: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const skeletonRows = [1, 2, 3, 4, 5, 6, 7, 8];
</script>

<template>
    <section
        class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">
                    Minhas inspeções
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Inspeções que exigem acompanhamento ou ação.
                </p>
            </div>

            <Link
                v-if="indexUrl"
                :href="indexUrl"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
            >
                Ver todas
            </Link>
        </div>

        <div v-if="loading" class="overflow-x-auto">
            <span class="sr-only" role="status">Carregando suas inspeções.</span>
            <table aria-hidden="true" class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">Carregando suas inspeções</caption>
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Inspeção</th>
                        <th scope="col" class="px-5 py-3">Cliente / Unidade</th>
                        <th scope="col" class="px-5 py-3">Equipamento</th>
                        <th scope="col" class="px-5 py-3">Minha função</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Prazo</th>
                        <th scope="col" class="px-5 py-3">Próxima ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="row in skeletonRows" :key="row" class="animate-pulse">
                        <td class="px-5 py-4"><div class="h-4 w-32 rounded bg-slate-200" /><div class="mt-2 h-3 w-24 rounded bg-slate-100" /></td>
                        <td class="px-5 py-4"><div class="h-4 w-28 rounded bg-slate-200" /><div class="mt-2 h-3 w-24 rounded bg-slate-100" /></td>
                        <td class="px-5 py-4"><div class="h-4 w-36 rounded bg-slate-200" /><div class="mt-2 h-3 w-20 rounded bg-slate-100" /></td>
                        <td class="px-5 py-4"><div class="h-4 w-24 rounded bg-slate-200" /><div class="mt-2 h-3 w-20 rounded bg-slate-100" /></td>
                        <td class="px-5 py-4"><div class="h-7 w-28 rounded-full bg-slate-200" /></td>
                        <td class="px-5 py-4"><div class="h-4 w-28 rounded bg-slate-200" /><div class="mt-2 h-3 w-20 rounded bg-slate-100" /></td>
                        <td class="px-5 py-4"><div class="ml-auto h-9 w-28 rounded-xl bg-slate-200" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="rows.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">Inspeções relacionadas às suas atribuições</caption>
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Inspeção</th>
                        <th scope="col" class="px-5 py-3">Cliente / Unidade</th>
                        <th scope="col" class="px-5 py-3">Equipamento</th>
                        <th scope="col" class="px-5 py-3">Minha função</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Prazo</th>
                        <th scope="col" class="px-5 py-3">Próxima ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="inspection in rows" :key="inspection.public_id" class="align-top">
                        <th scope="row" class="px-5 py-4 text-left font-normal">
                            <Link :href="inspection.next_action.href" class="font-semibold text-slate-900 transition hover:text-teal-700">
                                {{ inspection.number }}
                            </Link>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ inspection.inspection_type_label }} · criada em {{ inspection.created_at }}
                            </div>
                        </th>
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900">
                                {{ inspection.client.name }}
                            </div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ inspection.unit.name }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900">
                                {{ inspection.equipment.name }}
                            </div>
                            <div class="mt-1 text-sm font-semibold text-teal-700">
                                TAG {{ inspection.equipment.tag }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="responsibility in inspection.user_responsibilities"
                                    :key="responsibility.value"
                                    class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                >
                                    {{ responsibility.label }}
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <InspectionStatusBadge :status="inspection.status" />
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900">
                                {{ inspection.schedule.date }}
                            </div>
                            <div class="mt-1 text-sm" :class="inspection.schedule.is_overdue ? 'font-semibold text-rose-600' : 'text-slate-500'">
                                {{ inspection.schedule.label }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <Link
                                :href="inspection.next_action.href"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-100 hover:text-teal-800"
                            >
                                {{ inspection.next_action.label }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="px-5 py-10 text-center">
            <div class="text-base font-semibold text-slate-900">
                Nenhuma inspeção precisa da sua atenção.
            </div>
            <p class="mt-2 text-sm text-slate-500">
                Quando uma inspeção for atribuída a você, ela aparecerá aqui.
            </p>
        </div>
    </section>
</template>
