<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    counts: { type: Object, required: true },
    storage_url: { type: String, required: true },
});

const storage = ref(null);
const storageStatus = ref('loading');
const abortController = new AbortController();
const numberFormatter = new Intl.NumberFormat('pt-BR');

function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${units[unit]}`;
}

function formatMeasuredAt(value) {
    return new Date(value).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function formatFileCount(count) {
    return `${numberFormatter.format(count)} ${count === 1 ? 'arquivo' : 'arquivos'}`;
}

async function loadStorage() {
    storageStatus.value = 'loading';

    try {
        while (!abortController.signal.aborted) {
            const response = await fetch(props.storage_url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: abortController.signal,
            });

            if (response.status === 202) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                continue;
            }

            if (!response.ok) {
                throw new Error('Falha ao consultar o armazenamento.');
            }

            storage.value = await response.json();
            storageStatus.value = 'ready';
            return;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            storageStatus.value = 'error';
        }
    }
}

onMounted(loadStorage);
onBeforeUnmount(() => abortController.abort());
</script>

<template>
    <AppLayout title="Resumo da empresa" subtitle="Cadastros e espaço ocupado pelos arquivos das inspeções.">
        <section aria-labelledby="registrations-title">
            <div class="mb-4">
                <h2 id="registrations-title" class="text-lg font-semibold text-slate-950">Cadastros</h2>
                <p class="mt-1 text-sm text-slate-500">Totais atuais da empresa, incluindo registros inativos.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div v-for="item in [
                    { label: 'Usuários', value: counts.users },
                    { label: 'Clientes', value: counts.clients },
                    { label: 'Inspeções', value: counts.inspections },
                ]" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">{{ item.label }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-slate-950">{{ numberFormatter.format(item.value) }}</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="storage-title" class="mt-8">
            <div class="mb-4">
                <h2 id="storage-title" class="text-lg font-semibold text-slate-950">Armazenamento</h2>
                <p class="mt-1 text-sm text-slate-500">Fotografias e mapas armazenados pela empresa. A medição fica em cache por 8 horas.</p>
            </div>

            <div v-if="storageStatus === 'loading'" role="status" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                Calculando armazenamento…
            </div>
            <div v-else-if="storageStatus === 'error'" role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">
                <p>Não foi possível calcular o armazenamento agora.</p>
                <button type="button" class="mt-3 rounded-lg border border-rose-300 px-3 py-2 font-semibold hover:bg-rose-100" @click="loadStorage">Tentar novamente</button>
            </div>
            <template v-else-if="storage">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Total utilizado</p>
                    <p class="mt-2 text-4xl font-semibold tabular-nums text-slate-950">{{ formatBytes(storage.total_bytes) }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ formatFileCount(storage.total_file_count) }} · Atualizado em {{ formatMeasuredAt(storage.measured_at) }}</p>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div v-for="item in [
                        { label: 'Fotografias', usage: storage.photos },
                        { label: 'Mapas', usage: storage.maps },
                        { label: 'Identidade visual', usage: storage.branding },
                    ]" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">{{ item.label }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ formatBytes(item.usage.bytes) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ formatFileCount(item.usage.file_count) }}</p>
                    </div>
                </div>
            </template>
        </section>
    </AppLayout>
</template>
