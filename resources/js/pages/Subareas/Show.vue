<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';

defineProps({
    subarea: {
        type: Object,
        required: true,
    },
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
    can: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Subarea"
        subtitle="Detalhes cadastrais da ultima camada da hierarquia operacional."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ subarea.name }}</h2>
                        <StatusBadge :status="subarea.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        <Link :href="client.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ client.name }}</Link>
                        /
                        <Link :href="unit.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ unit.name }}</Link>
                        /
                        <Link :href="area.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ area.name }}</Link>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.update"
                        :href="subarea.edit_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Editar
                    </Link>
                    <StatusToggleForm
                        v-if="can.update"
                        :action="subarea.status_url"
                        :current-status="subarea.status"
                        entity-label="subarea"
                    />
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ subarea.code ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <StatusBadge :status="subarea.status" />
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 xl:col-span-1">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ subarea.description ?? '-' }}</dd>
                </div>
            </dl>
        </section>
    </AppLayout>
</template>
