<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';
import AreaList from '@/components/domain/client-units/AreaList.vue';

defineProps({
    unit: {
        type: Object,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    areas: {
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
        title="Unidade"
        subtitle="Detalhes cadastrais e areas vinculadas."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ unit.name }}</h2>
                        <StatusBadge :status="unit.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        Cliente
                        <Link :href="client.show_url" class="font-medium text-teal-700 hover:text-teal-800">
                            {{ client.name }}
                        </Link>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.update"
                        :href="unit.edit_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Editar
                    </Link>
                    <StatusToggleForm
                        v-if="can.update"
                        :action="unit.status_url"
                        :current-status="unit.status"
                        entity-label="unidade"
                    />
                    <Link
                        v-if="can.create_area"
                        :href="unit.create_area_url"
                        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                    >
                        Nova area
                    </Link>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ unit.code ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timezone</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ unit.timezone ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ unit.city ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <StatusBadge :status="unit.status" />
                    </dd>
                </div>
            </dl>

            <div v-if="unit.address_line || unit.address_number || unit.district || unit.state || unit.country_code" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endereco</div>
                <p class="mt-2 text-sm text-slate-700">
                    {{ unit.address_line ?? '' }}
                    <span v-if="unit.address_number">, {{ unit.address_number }}</span>
                    <span v-if="unit.district"> - {{ unit.district }}</span>
                    <span v-if="unit.city"> - {{ unit.city }}</span>
                    <span v-if="unit.state"> / {{ unit.state }}</span>
                    <span v-if="unit.country_code"> - {{ unit.country_code }}</span>
                </p>
            </div>
        </section>

        <div class="mt-6">
            <AreaList :areas="areas" />
        </div>
    </AppLayout>
</template>
