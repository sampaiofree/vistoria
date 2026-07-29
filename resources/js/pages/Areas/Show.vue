<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';
import SubareaList from '@/components/domain/areas/SubareaList.vue';

defineProps({
    area: {
        type: Object,
        required: true,
    },
    unit: {
        type: Object,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    subareas: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Area"
        subtitle="Detalhes cadastrais e subareas vinculadas."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ area.name }}</h2>
                        <StatusBadge :status="area.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        <Link :href="client.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ client.name }}</Link>
                        /
                        <Link :href="unit.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ unit.name }}</Link>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.update"
                        :href="area.edit_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Editar
                    </Link>
                    <StatusToggleForm
                        v-if="can.update"
                        :action="area.status_url"
                        :current-status="area.status"
                        entity-label="area"
                    />
                    <Link
                        v-if="can.create_subarea"
                        :href="area.create_subarea_url"
                        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                    >
                        Nova subarea
                    </Link>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ area.code ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <StatusBadge :status="area.status" />
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ area.description ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-6">
            <SubareaList :subareas="subareas" />
        </div>
    </AppLayout>
</template>
