<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    preview: { type: Object, default: null },
    result: { type: Object, default: null },
    field_options: { type: Array, required: true },
    preview_url: { type: String, required: true },
    confirm_url: { type: String, required: true },
    index_url: { type: String, required: true },
    client_action: { type: Object, default: null },
});

const uploadForm = useForm({ file: null });
const mappingForm = useForm({
    token: props.preview?.token ?? '',
    mapping: props.preview?.mapping ?? {},
});

const requiredFields = computed(() => props.field_options.filter((field) => field.required));
const canConfirm = computed(() => requiredFields.value.every((field) => mappingForm.mapping[field.value]));

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
    mappingForm.post(props.confirm_url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout
        title="Importar itens de manutenção"
        subtitle="Envie o CSV, confira o mapeamento e confirme os registros válidos."
    >
        <section v-if="!preview && !result" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Enviar CSV</h2>
            <p class="mt-1 text-sm text-slate-500">
                São aceitos CSV UTF-8 separados por ponto e vírgula ou vírgula, com até 5.000 registros.
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

                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Item manutenção, TAG, Denominação do loc.instalação e Prefixo de avaria precisam ser mapeados. O prefixo é obrigatório em cada linha.
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
                <p class="mt-1 text-sm text-slate-500">{{ preview.total }} registro(s) encontrados. Revise as colunas antes de importar.</p>

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
                            {{ mappingForm.processing ? 'Importando...' : 'Confirmar importação' }}
                        </button>
                    </div>
                </form>
            </section>
        </template>

        <section v-else class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Resultado da importação</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-sm text-slate-500">Linhas lidas</dt><dd class="mt-1 text-2xl font-semibold">{{ result.total }}</dd></div>
                <div class="rounded-xl bg-emerald-50 p-4"><dt class="text-sm text-emerald-700">Equipamentos criados</dt><dd class="mt-1 text-2xl font-semibold text-emerald-900">{{ result.created }}</dd></div>
                <div class="rounded-xl bg-rose-50 p-4"><dt class="text-sm text-rose-700">Linhas não registradas</dt><dd class="mt-1 text-2xl font-semibold text-rose-900">{{ result.rejected.length }}</dd></div>
            </dl>

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
