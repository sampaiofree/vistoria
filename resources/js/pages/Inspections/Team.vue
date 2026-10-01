<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AssignmentForm from '@/components/domain/inspections/AssignmentForm.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';

const props = defineProps({
    inspection: { type: Object, required: true },
    responsibles: { type: Array, default: () => [] },
    capabilities: { type: Object, default: () => ({}) },
    assignment_options: { type: Object, default: () => ({ users: [], roles: [] }) },
    tabs: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'team' },
    back_url: { type: String, required: true },
});

const showAddForm = ref(false);
const roleGroups = computed(() => props.assignment_options.roles
    .map((role) => ({
        ...role,
        responsibles: props.responsibles.filter((item) => item.responsibility === role.value),
    })));

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

</script>

<template>
    <AppLayout
        title="Equipe e responsáveis"
        :subtitle="(inspection.number || 'Inspeção') + ' · ' + inspection.equipment.tag + ' — ' + inspection.equipment.name"
        wide
    >
        <template #actions>
            <InspectionStatusBadge :status="inspection.status" />
            <Link :href="back_url" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300">Voltar</Link>
        </template>

        <div class="lg:hidden">
            <InspectionTabs :tabs="tabs" :active="active_tab" />
        </div>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">{{ responsibles.length }} responsabilidade(s) definida(s)</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Responsáveis pela inspeção</h2>
                    <p class="mt-1 text-sm text-slate-500">Cada função possui somente um responsável.</p>
                </div>
                <button
                    v-if="capabilities.assign"
                    type="button"
                    class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800"
                    @click="showAddForm = !showAddForm"
                >
                    {{ showAddForm ? 'Fechar' : 'Definir responsável' }}
                </button>
            </div>

            <div v-if="showAddForm && capabilities.assign" class="mt-5 rounded-2xl border border-teal-100 bg-teal-50/50 p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-950">Definir responsável</h3>
                    <span class="text-xs text-slate-500">A nova definição substitui o responsável atual da função.</span>
                </div>
                <AssignmentForm
                    :action="capabilities.assign.action"
                    :users="assignment_options.users"
                    :roles="assignment_options.roles"
                />
            </div>
        </section>

        <section v-if="roleGroups.length" class="mt-6 space-y-4">
            <article v-for="group in roleGroups" :key="group.value" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-slate-950">{{ group.label }}</h2>
                </div>
                <ul v-if="group.responsibles.length" class="divide-y divide-slate-100">
                    <li v-for="item in group.responsibles" :key="item.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-50 text-xs font-bold text-teal-700">{{ initials(item.user.name) }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-950">{{ item.user.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">Atribuído em {{ item.assigned_at || 'data não informada' }}</p>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="px-5 py-4 text-sm text-slate-500 sm:px-6">Responsável não definido.</p>
            </article>
        </section>

        <section v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="font-semibold text-slate-950">Nenhuma função disponível</h2>
        </section>
    </AppLayout>
</template>
