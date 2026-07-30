<script setup>
import { computed } from 'vue';
import { Deferred, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import UiIcon from '@/components/ui/UiIcon.vue';
import PriorityCard from '@/components/dashboard/PriorityCard.vue';
import MyInspectionsTable from '@/components/dashboard/MyInspectionsTable.vue';
import WorkflowSummary from '@/components/dashboard/WorkflowSummary.vue';
import RecentActivities from '@/components/dashboard/RecentActivities.vue';
import DashboardLoadError from '@/components/dashboard/DashboardLoadError.vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    organization: {
        type: Object,
        default: null,
    },
    can: {
        type: Object,
        required: true,
    },
    links: {
        type: Object,
        required: true,
    },
    priority_counts: {
        type: Object,
        default: null,
    },
    my_inspections: {
        type: Array,
        default: null,
    },
    workflow_summary: {
        type: Array,
        default: null,
    },
    recent_activities: {
        type: Array,
        default: null,
    },
});

const title = 'Dashboard';

const subtitle = computed(() => {
    if (props.mode === 'global') {
        return 'Visão global do ambiente sem organização operacional vinculada.';
    }

    return 'Acompanhe suas pendências e o andamento das inspeções.';
});

const priorityCards = [
    {
        key: 'overdue',
        title: 'Atrasadas',
        description: 'Planejadas fora do prazo',
        icon: 'clock',
        variant: 'danger',
    },
    {
        key: 'awaiting_review',
        title: 'Aguardando revisão',
        description: 'Inspeções prontas para validação',
        icon: 'review',
        variant: 'info',
    },
    {
        key: 'in_correction',
        title: 'Devolvidas para correção',
        description: 'Itens que precisam de ajuste',
        icon: 'correction',
        variant: 'warning',
    },
    {
        key: 'awaiting_approval',
        title: 'Aguardando aprovação',
        description: 'Prontas para decisão final',
        icon: 'approval',
        variant: 'approval',
    },
];

function retry(prop) {
    router.reload({
        only: [prop],
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="title" :subtitle="subtitle" wide>
        <template #actions>
            <Link
                v-if="can.create_inspection && links.inspections_create"
                :href="links.inspections_create"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/80"
            >
                + Nova inspeção
            </Link>
        </template>

        <section
            v-if="mode === 'global'"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5"
        >
            <div class="flex items-start gap-4">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <UiIcon name="dashboard" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Visão global sem organização operacional
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Superadministradores continuam com acesso ao ambiente, mas a central operacional aparece apenas quando uma organização for selecionada ou vinculada.
                    </p>
                </div>
            </div>
        </section>

        <template v-else>
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Deferred data="priority_counts">
                    <template #fallback>
                        <span class="sr-only" role="status">Carregando prioridades.</span>
                        <PriorityCard
                            v-for="card in priorityCards"
                            :key="card.key"
                            :title="card.title"
                            :description="card.description"
                            :icon="card.icon"
                            :variant="card.variant"
                            loading
                        />
                    </template>

                    <template #rescue="{ reloading }">
                        <DashboardLoadError
                            class="md:col-span-2 xl:col-span-4"
                            title="Não foi possível carregar as prioridades."
                            :retrying="reloading"
                            @retry="retry('priority_counts')"
                        />
                    </template>

                    <PriorityCard
                        v-for="card in priorityCards"
                        :key="card.key"
                        :title="card.title"
                        :description="card.description"
                        :icon="card.icon"
                        :variant="card.variant"
                        :href="links.priority[card.key]"
                        :count="priority_counts?.[card.key] ?? 0"
                    />
                </Deferred>
            </section>

            <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,2.25fr)_minmax(20rem,1fr)]">
                <Deferred data="my_inspections">
                    <template #fallback>
                        <MyInspectionsTable loading :index-url="links.inspections_index" />
                    </template>

                    <template #rescue="{ reloading }">
                        <DashboardLoadError
                            title="Não foi possível carregar suas inspeções."
                            :retrying="reloading"
                            @retry="retry('my_inspections')"
                        />
                    </template>

                    <MyInspectionsTable
                        :rows="my_inspections"
                        :index-url="links.inspections_index"
                    />
                </Deferred>

                <div class="space-y-6">
                    <Deferred data="workflow_summary">
                        <template #fallback>
                            <WorkflowSummary
                                loading
                                :company-summary="can.view_company_summary"
                            />
                        </template>

                        <template #rescue="{ reloading }">
                            <DashboardLoadError
                                title="Não foi possível carregar o fluxo operacional."
                                :retrying="reloading"
                                @retry="retry('workflow_summary')"
                            />
                        </template>

                        <WorkflowSummary
                            :steps="workflow_summary"
                            :company-summary="can.view_company_summary"
                        />
                    </Deferred>

                    <Deferred data="recent_activities">
                        <template #fallback>
                            <RecentActivities loading />
                        </template>

                        <template #rescue="{ reloading }">
                            <DashboardLoadError
                                title="Não foi possível carregar as atividades recentes."
                                :retrying="reloading"
                                @retry="retry('recent_activities')"
                            />
                        </template>

                        <RecentActivities :activities="recent_activities" />
                    </Deferred>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
