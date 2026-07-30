<script setup>
import { Link } from '@inertiajs/vue3';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

defineProps({
    subareas: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-sm font-semibold text-slate-900">Subareas</div>
            <div class="text-sm text-slate-500">{{ subareas.total }} registro(s)</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-3">Subarea</th>
                        <th class="px-5 py-3">Codigo</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="subarea in subareas.data" :key="subarea.public_id">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ subarea.name }}</div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ subarea.code ?? '-' }}
                        </td>
                        <td class="px-5 py-4">
                            <StatusBadge :status="subarea.status" />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="subarea.show_url"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Ver
                                </Link>
                                <Link
                                    v-if="subarea.can_update"
                                    :href="subarea.edit_url"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Editar
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="subareas.data.length === 0">
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Nenhuma subarea encontrada.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            <Pagination :links="subareas.links" />
        </div>
    </section>
</template>
