<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CorrectionRequestCard from '@/components/domain/inspections/CorrectionRequestCard.vue';

const props = defineProps({
    correction: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Solicitações de ajuste' },
    empty_label: { type: String, default: 'Nenhuma solicitação registrada.' },
});

const panelOpen = ref(false);
const activeTab = ref('open');
const form = useForm({ request_message: '' });
const openItems = computed(() => props.correction?.items ?? []);
const historyItems = computed(() => props.correction?.history ?? []);
const items = computed(() => activeTab.value === 'open' ? openItems.value : historyItems.value);
const openCount = computed(() => props.correction?.counts?.open ?? openItems.value.length);
const historyCount = computed(() => props.correction?.counts?.history ?? historyItems.value.length);
const assessmentOptions = computed(() => props.correction?.assessment_options ?? []);

function create() {
    if (!props.correction?.create_url) return;
    form.post(props.correction.create_url, { preserveScroll: true, onSuccess: () => { form.reset(); panelOpen.value = false; } });
}
</script>

<template>
    <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-800">{{ title }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    <template v-if="activeTab === 'open'">{{ openCount ? `${openCount} solicitação(ões) em andamento.` : 'Nenhuma solicitação em andamento.' }}</template>
                    <template v-else>{{ historyCount ? `${historyCount} solicitação(ões) encerrada(s) ou substituída(s).` : empty_label }}</template>
                </p>
            </div>
            <button v-if="correction.create_url" type="button" class="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800" @click="panelOpen = !panelOpen; form.reset()">Solicitar ajuste</button>
        </div>

        <form v-if="correction.create_url && panelOpen" class="mt-4 border-t border-amber-200 pt-4" @submit.prevent="create">
            <label class="block text-sm font-medium text-slate-800">Mensagem para o responsável pelo ajuste<textarea v-model="form.request_message" required minlength="10" rows="3" class="mt-1 w-full rounded-lg border border-amber-300 bg-white px-3 py-2" /></label>
            <p v-if="form.errors.request_message" class="mt-1 text-xs text-rose-700">{{ form.errors.request_message }}</p>
            <button :disabled="form.processing" class="mt-3 rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">Marcar avaria</button>
        </form>

        <div class="mt-4 border-t border-amber-200 pt-4">
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filtrar solicitações de correção">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'open'"
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'open' ? 'bg-amber-700 text-white' : 'bg-white text-amber-900 hover:bg-amber-100'"
                    @click="activeTab = 'open'"
                >
                    Em andamento ({{ openCount }})
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'history'"
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'history' ? 'bg-amber-700 text-white' : 'bg-white text-amber-900 hover:bg-amber-100'"
                    @click="activeTab = 'history'"
                >
                    Histórico ({{ historyCount }})
                </button>
            </div>

            <div v-if="items.length" class="mt-4 space-y-3">
                <CorrectionRequestCard v-for="request in items" :key="request.public_id" :request="request" :assessment-options="assessmentOptions" />
            </div>
            <div v-else class="mt-4 rounded-xl border border-dashed border-amber-300 bg-white/70 p-3 text-sm text-slate-600">
                <template v-if="activeTab === 'open' && historyCount">
                    Nenhuma solicitação em andamento.
                    <button type="button" class="ml-1 font-semibold text-amber-900 underline underline-offset-2" @click="activeTab = 'history'">Ver histórico ({{ historyCount }})</button>
                </template>
                <template v-else>{{ activeTab === 'open' ? 'Nenhuma solicitação em andamento.' : empty_label }}</template>
            </div>
        </div>
    </section>
</template>
