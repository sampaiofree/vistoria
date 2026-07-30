<script setup>
import { Link } from '@inertiajs/vue3';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

defineProps({
    units: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-sm font-semibold text-slate-900">Unidades</div>
            <div class="text-sm text-slate-500">{{ units.total }} registro(s)</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-3">Unidade</th>
                        <th class="px-5 py-3">Codigo</th>
                        <th class="px-5 py-3">Areas</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="unit in units.data" :key="unit.public_id">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ unit.name }}</div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ unit.code ?? '-' }}
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ unit.areas_count }}
                        </td>
                        <td class="px-5 py-4">
                            <StatusBadge :status="unit.status" />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="unit.show_url"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Ver
                                </Link>
                                <Link
                                    v-if="unit.can_update"
                                    :href="unit.edit_url"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Editar
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="units.data.length === 0">
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                            Nenhuma unidade encontrada.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            <Pagination :links="units.links" />
        </div>
    </section>
</template>
