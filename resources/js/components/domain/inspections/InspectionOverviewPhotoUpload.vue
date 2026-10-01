<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { optimizeImageForUpload } from '@/lib/imageUploadOptimizer.js';

const props = defineProps({
    action: { type: String, required: true },
    available: { type: Number, required: true },
});

const maxFiles = 10;
const maxFileSize = 25 * 1024 * 1024;
const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp'];
const cameraInput = ref(null);
const galleryInput = ref(null);
const queue = ref([]);
const selectionError = ref('');
const preparing = ref(false);
const processing = ref(false);
const currentPosition = ref(0);
const uploadTotal = ref(0);
let sequence = 0;
let disposed = false;

const waitingCount = computed(() => queue.value.filter((item) => item.status === 'waiting').length);
const sentCount = computed(() => queue.value.filter((item) => item.status === 'sent').length);
const failedCount = computed(() => queue.value.filter((item) => item.status === 'failed').length);
const busy = computed(() => preparing.value || processing.value);

function release(item) {
    if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    item.previewUrl = null;
}

function clearQueue() {
    queue.value.forEach(release);
    queue.value = [];
}

function validationError(file) {
    if (file.type && !acceptedTypes.includes(file.type)) return 'Formato não permitido. Use JPG, PNG ou WebP.';
    if (file.size > maxFileSize) return 'O arquivo excede o limite de 25 MB.';
    return null;
}

async function selectFiles(event) {
    if (busy.value) return;

    const files = Array.from(event.target.files ?? []);
    event.target.value = '';
    selectionError.value = '';
    if (!files.length) return;

    if (queue.value.length && queue.value.every((item) => ['sent', 'failed'].includes(item.status))) clearQueue();

    const remaining = Math.min(maxFiles, props.available) - queue.value.length;
    if (files.length > remaining) {
        selectionError.value = `Selecione no máximo ${Math.max(0, remaining)} imagem(ns) adicionais neste envio.`;
        return;
    }

    const added = files.map((file) => ({
        id: `${Date.now()}-${++sequence}`,
        file,
        previewUrl: null,
        status: 'preparing',
        error: null,
        warning: null,
    }));
    queue.value.push(...added);
    preparing.value = true;

    try {
        for (const item of added) {
            const initialError = item.file.type && !acceptedTypes.includes(item.file.type)
                ? 'Formato não permitido. Use JPG, PNG ou WebP.'
                : null;
            if (initialError) {
                item.status = 'failed';
                item.error = initialError;
                continue;
            }

            try {
                const result = await optimizeImageForUpload(item.file);
                if (disposed) return;
                item.file = result.file;
                item.warning = result.warning;
                item.error = validationError(item.file);
                item.status = item.error ? 'failed' : 'waiting';
                item.previewUrl = item.file.type.startsWith('image/') ? URL.createObjectURL(item.file) : null;
            } catch {
                item.status = 'failed';
                item.error = 'Não foi possível preparar esta imagem. Selecione outra e tente novamente.';
            }
        }
    } finally {
        preparing.value = false;
    }
}

function removeItem(id) {
    if (busy.value) return;
    const index = queue.value.findIndex((item) => item.id === id);
    if (index === -1 || !['waiting', 'failed'].includes(queue.value[index].status)) return;
    release(queue.value[index]);
    queue.value.splice(index, 1);
}

function submit() {
    if (busy.value || waitingCount.value === 0) return;
    processing.value = true;
    currentPosition.value = 0;
    uploadTotal.value = waitingCount.value;
    uploadNext();
}

function uploadNext() {
    const item = queue.value.find((candidate) => candidate.status === 'waiting');
    if (!item) {
        processing.value = false;
        currentPosition.value = 0;
        uploadTotal.value = 0;
        queue.value.forEach(release);
        return;
    }

    item.status = 'uploading';
    currentPosition.value += 1;
    router.post(props.action, { file: item.file }, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        only: ['overview', 'flash'],
        onSuccess: () => { item.status = 'sent'; },
        onError: (errors) => {
            item.status = 'failed';
            item.error = errors.file ?? Object.values(errors)[0] ?? 'Não foi possível enviar esta imagem.';
        },
        onFinish: () => uploadNext(),
    });
}

onBeforeUnmount(() => {
    disposed = true;
    clearQueue();
});
</script>

<template>
    <form class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-4" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-950">Adicionar fotografias</p>
                <p class="mt-1 text-xs text-slate-600">Selecione até 10 imagens por envio. As fotos são acrescentadas na ordem da seleção.</p>
            </div>
            <button type="submit" :disabled="busy || waitingCount === 0" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50">
                {{ preparing ? 'Preparando imagens…' : (processing ? `Enviando ${currentPosition}/${uploadTotal}…` : `Enviar ${waitingCount} foto(s)`) }}
            </button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <input ref="cameraInput" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" :disabled="busy" class="sr-only" @change="selectFiles">
            <button type="button" :disabled="busy" class="camera-action rounded-md bg-slate-800 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" @click="cameraInput?.click()">Tirar foto</button>
            <input ref="galleryInput" type="file" multiple accept="image/jpeg,image/png,image/webp" :disabled="busy" class="sr-only" @change="selectFiles">
            <button type="button" :disabled="busy" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50" @click="galleryInput?.click()">Escolher da galeria</button>
        </div>
        <p v-if="selectionError" class="mt-2 text-xs font-medium text-rose-700">{{ selectionError }}</p>
        <div v-if="queue.length" class="mt-4 space-y-2" aria-live="polite">
            <div v-for="item in queue" :key="item.id" class="flex items-center gap-3 rounded-md border border-slate-200 bg-white p-2 text-sm">
                <img v-if="item.previewUrl" :src="item.previewUrl" alt="Prévia da fotografia selecionada" class="h-14 w-16 shrink-0 rounded bg-slate-100 object-cover">
                <div v-else class="h-14 w-16 shrink-0 rounded bg-slate-100"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-slate-800">{{ item.file.name }}</p>
                    <p class="text-xs text-slate-500">{{ { preparing: 'Preparando', waiting: 'Aguardando', uploading: 'Enviando', sent: 'Enviada', failed: 'Falhou' }[item.status] }}</p>
                    <p v-if="item.warning" class="text-xs text-amber-700">{{ item.warning }}</p>
                    <p v-if="item.error" class="text-xs text-rose-700">{{ item.error }}</p>
                </div>
                <button v-if="!busy && ['waiting', 'failed'].includes(item.status)" type="button" class="text-xs font-semibold text-rose-700" @click="removeItem(item.id)">Remover</button>
            </div>
        </div>
        <p v-if="!processing && queue.length && (sentCount || failedCount)" class="mt-3 text-xs text-slate-600">{{ sentCount }} enviada(s) com sucesso<span v-if="failedCount">; {{ failedCount }} com falha</span>.</p>
    </form>
</template>

<style scoped>
.camera-action { display: none; }
@media (hover: none) and (pointer: coarse) { .camera-action { display: inline-flex; } }
</style>
