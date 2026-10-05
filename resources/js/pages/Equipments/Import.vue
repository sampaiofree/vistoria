<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    preview: { type: Object, default: null },
    result: { type: Object, default: null },
    review: { type: Object, default: null },
    mode: { type: String, default: 'create' },
    field_options: { type: Array, required: true },
    preview_url: { type: String, required: true },
    confirm_url: { type: String, required: true },
    plan_update_url: { type: String, required: true },
    confirm_update_url: { type: String, required: true },
    create_mode_url: { type: String, required: true },
    update_mode_url: { type: String, required: true },
    index_url: { type: String, required: true },
    client_action: { type: Object, default: null },
});

const uploadForm = useForm({ file: null, mode: props.mode });
const mappingForm = useForm({
    token: props.preview?.token ?? '',
    mapping: props.preview?.mapping ?? {},
});
const reviewForm = useForm({
    token: props.review?.token ?? '',
    confirm_related_records_edit: false,
});

const requiredFields = computed(() => props.field_options.filter((field) => field.required));
const canConfirm = computed(() => requiredFields.value.every((field) => mappingForm.mapping[field.value])
    && (props.mode !== 'update' || Object.entries(mappingForm.mapping).some(([field, column]) => field !== 'maintenance_item_code' && column)));

watch(() => props.mode, (mode) => {
    uploadForm.mode = mode;
    uploadForm.file = null;
});

watch(() => props.review?.token ?? null, (token) => {
    reviewForm.token = token ?? '';
    reviewForm.confirm_related_records_edit = false;
    reviewForm.clearErrors();
});

watch(
    () => props.preview?.token ?? null,
    (token, previousToken) => {
        if (token === null || token === previousToken) {
            return;
        }

        mappingForm.token = token;
        mappingForm.mapping = { ...(props.preview?.mapping ?? {}) };
        mappingForm.clearErrors();
    },
);

function selectFile(event) {
    uploadForm.file = event.target.files?.[0] ?? null;
}

function previewFile() {
    uploadForm.post(props.preview_url, { preserveScroll: true });
}

function confirmImport() {
    mappingForm.post(props.mode === 'update' ? props.plan_update_url : props.confirm_url, { preserveScroll: true });
}

function confirmUpdate() {
    reviewForm.post(props.confirm_update_url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout
        :title="mode === 'update' ? 'Atualizar itens de manutenção' : 'Importar itens de manutenção'"
        :subtitle="mode === 'update' ? 'Atualize equipamentos existentes a partir do CSV.' : 'Envie o CSV, confira o mapeamento e confirme os registros válidos.'"
    >
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Modo de importação">
            <Link :href="create_mode_url" :class="mode === 'create' ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 bg-white text-slate-700'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cadastrar novos</Link>
            <Link :href="update_mode_url" :class="mode === 'update' ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 bg-white text-slate-700'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Atualizar existentes</Link>
        </nav>

        <section v-if="!preview && !result && !review" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Enviar CSV</h2>
            <p class="mt-1 text-sm text-slate-500">
                São aceitos CSV UTF-8 separados por ponto e vírgula ou vírgula, com até 5.000 registros.
            </p>
            <p v-if="mode === 'update'" class="mt-2 text-sm text-slate-600">
                O Item manutenção identifica o equipamento. Células vazias preservam o valor atual; itens não encontrados não serão criados.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="previewFile">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Arquivo CSV</span>
                    <input
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        @change="selectFile"
                    >
                    <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.file }}</p>
                </label>

                <p v-if="mode === 'create'" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Item manutenção, TAG, Denominação do loc.instalação e Prefixo de avaria precisam ser mapeados. O prefixo é obrigatório em cada linha.
                </p>
                <p v-else class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Mapeie Item manutenção e pelo menos uma coluna para atualizar. O código do item não será alterado.
                </p>

                <div class="flex flex-wrap justify-between gap-3">
                    <Link :href="index_url" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</Link>
                    <button type="submit" :disabled="uploadForm.processing || !uploadForm.file" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">
                        Ler arquivo
                    </button>
                </div>
            </form>
        </section>

        <template v-else-if="preview">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Conferir mapeamento</h2>
                <p class="mt-1 text-sm text-slate-500">{{ preview.total }} registro(s) encontrados. Revise as colunas antes de {{ mode === 'update' ? 'preparar a atualização' : 'importar' }}.</p>

                <form class="mt-6 space-y-5" @submit.prevent="confirmImport">
                    <div v-if="mappingForm.errors.client" role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        <p>{{ mappingForm.errors.client }}</p>
                        <Link
                            v-if="client_action"
                            :href="client_action.url"
                            class="rounded-lg border border-rose-300 bg-white px-3 py-2 font-semibold text-rose-800"
                        >
                            {{ client_action.label }}
                        </Link>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label v-for="field in field_options" :key="field.value" class="block">
                            <span class="text-sm font-medium text-slate-700">
                                {{ field.label }} <span v-if="field.required" class="text-rose-600">*</span>
                            </span>
                            <select v-model="mappingForm.mapping[field.value]" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option :value="null">Não importar</option>
                                <option v-for="column in preview.columns" :key="column.key" :value="column.key">{{ column.label }}</option>
                            </select>
                            <p v-if="mappingForm.errors[`mapping.${field.value}`]" class="mt-1 text-xs text-rose-600">{{ mappingForm.errors[`mapping.${field.value}`] }}</p>
                        </label>
                    </div>
                    <p v-if="mappingForm.errors.mapping" class="text-sm text-rose-600">{{ mappingForm.errors.mapping }}</p>
                    <p v-if="mappingForm.errors.token" class="text-sm text-rose-600">{{ mappingForm.errors.token }}</p>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Linha</th>
                                    <th v-for="column in preview.columns" :key="column.key" class="px-3 py-2">{{ column.label }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="row in preview.samples" :key="row.line">
                                    <td class="px-3 py-2 text-slate-500">{{ row.line }}</td>
                                    <td v-for="column in preview.columns" :key="column.key" class="max-w-48 truncate px-3 py-2">{{ row.values[column.key] || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap justify-between gap-3">
                        <Link :href="index_url" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</Link>
                        <button type="submit" :disabled="mappingForm.processing || !canConfirm" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">
                            {{ mappingForm.processing ? 'Processando...' : (mode === 'update' ? 'Revisar alterações' : 'Confirmar importação') }}
                        </button>
                    </div>
                </form>
            </section>
        </template>

        <section v-else-if="review" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Revisar atualização</h2>
            <p class="mt-1 text-sm text-slate-600">Confira as alterações antes de gravar. Equipamentos alterados após esta prévia serão rejeitados na confirmação.</p>
            <dl class="mt-5 grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-sm text-slate-500">Linhas lidas</dt><dd class="mt-1 text-2xl font-semibold">{{ review.summary.total }}</dd></div>
                <div class="rounded-xl bg-emerald-50 p-4"><dt class="text-sm text-emerald-700">Prontas para atualizar</dt><dd class="mt-1 text-2xl font-semibold text-emerald-900">{{ review.summary.ready }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-sm text-slate-500">Sem alteração</dt><dd class="mt-1 text-2xl font-semibold">{{ review.summary.unchanged }}</dd></div>
                <div class="rounded-xl bg-rose-50 p-4"><dt class="text-sm text-rose-700">Rejeitadas</dt><dd class="mt-1 text-2xl font-semibold text-rose-900">{{ review.summary.rejected }}</dd></div>
            </dl>

            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Linha</th><th class="px-3 py-2">Item manutenção</th><th class="px-3 py-2">Situação e alterações</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in review.rows" :key="row.line">
                            <td class="px-3 py-2 align-top">{{ row.line }}</td>
                            <td class="px-3 py-2 align-top">{{ row.maintenance_item_code || '—' }}<span v-if="row.tag" class="block text-xs text-slate-500">TAG {{ row.tag }}</span></td>
                            <td class="px-3 py-2">
                                <span v-if="row.status === 'unchanged'" class="text-slate-600">Sem alteração</span>
                                <span v-else-if="row.status === 'rejected'" class="text-rose-700">{{ row.reason }}</span>
                                <div v-else class="space-y-1">
                                    <p v-if="row.related" class="font-medium text-amber-800">Possui inspeções ou avarias vinculadas</p>
                                    <p v-for="change in row.changes" :key="change.field"><span class="font-medium">{{ change.label }}:</span> {{ change.from || '—' }} → {{ change.to }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="review.pages > 1" class="mt-4 flex items-center justify-between gap-4 text-sm">
                <Link v-if="review.previous_url" :href="review.previous_url" class="font-semibold text-teal-700">Anterior</Link><span v-else />
                <span>Página {{ review.page }} de {{ review.pages }}</span>
                <Link v-if="review.next_url" :href="review.next_url" class="font-semibold text-teal-700">Próxima</Link><span v-else />
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="confirmUpdate">
                <label v-if="review.summary.related > 0" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <input v-model="reviewForm.confirm_related_records_edit" type="checkbox" class="mt-0.5">
                    <span>Confirmo a atualização de {{ review.summary.related }} equipamento(s) com inspeções ou avarias. Os relatórios anteriores preservam seus dados históricos.</span>
                </label>
                <p v-if="reviewForm.errors.confirm_related_records_edit" class="text-sm text-rose-700">{{ reviewForm.errors.confirm_related_records_edit }}</p>
                <p v-if="reviewForm.errors.token" class="text-sm text-rose-700">{{ reviewForm.errors.token }}</p>
                <div class="flex flex-wrap justify-between gap-3">
                    <Link :href="update_mode_url" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</Link>
                    <button type="submit" :disabled="reviewForm.processing || review.summary.ready === 0 || (review.summary.related > 0 && !reviewForm.confirm_related_records_edit)" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">{{ reviewForm.processing ? 'Atualizando...' : 'Confirmar atualização' }}</button>
                </div>
            </form>
        </section>

        <section v-else class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ mode === 'update' ? 'Resultado da atualização' : 'Resultado da importação' }}</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-sm text-slate-500">Linhas lidas</dt><dd class="mt-1 text-2xl font-semibold">{{ result.total }}</dd></div>
                <div class="rounded-xl bg-emerald-50 p-4"><dt class="text-sm text-emerald-700">Equipamentos {{ mode === 'update' ? 'atualizados' : 'criados' }}</dt><dd class="mt-1 text-2xl font-semibold text-emerald-900">{{ mode === 'update' ? result.updated : result.created }}</dd></div>
                <div class="rounded-xl bg-rose-50 p-4"><dt class="text-sm text-rose-700">Linhas rejeitadas</dt><dd class="mt-1 text-2xl font-semibold text-rose-900">{{ result.rejected.length }}</dd></div>
            </dl>
            <p v-if="mode === 'update'" class="mt-3 text-sm text-slate-600">{{ result.unchanged }} linha(s) sem alteração.</p>

            <div v-if="result.rejected.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Linha</th><th class="px-3 py-2">Item manutenção</th><th class="px-3 py-2">TAG</th><th class="px-3 py-2">Motivo</th></tr></thead>
                    <tbody class="divide-y divide-slate-200"><tr v-for="row in result.rejected" :key="row.line"><td class="px-3 py-2">{{ row.line }}</td><td class="px-3 py-2">{{ row.maintenance_item_code || '—' }}</td><td class="px-3 py-2">{{ row.tag || '—' }}</td><td class="px-3 py-2 text-rose-700">{{ row.reason }}</td></tr></tbody>
                </table>
            </div>

            <div class="mt-6"><Link :href="index_url" class="inline-flex items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Voltar aos equipamentos</Link></div>
        </section>
    </AppLayout>
</template>
