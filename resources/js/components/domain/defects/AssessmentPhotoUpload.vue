<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    action: { type: String, required: true },
});

const maxFiles = 10;
const maxFileSize = 25 * 1024 * 1024;
const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp'];
const fileInput = ref(null);
const queue = ref([]);
const selectionError = ref('');
const processing = ref(false);
const currentPosition = ref(0);

const waitingCount = computed(() => queue.value.filter((item) => item.status === 'waiting').length);
const sentCount = computed(() => queue.value.filter((item) => item.status === 'sent').length);
const failedCount = computed(() => queue.value.filter((item) => item.status === 'failed').length);

function statusLabel(status) {
    return {
        waiting: 'Aguardando',
        uploading: 'Enviando',
        sent: 'Enviada',
        failed: 'Falhou',
    }[status] ?? status;
}

function statusClasses(status) {
    return {
        waiting: 'bg-slate-100 text-slate-700',
        uploading: 'bg-sky-100 text-sky-800',
        sent: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-rose-100 text-rose-800',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

function fileSize(size) {
    if (size < 1024 * 1024) {
        return `${Math.max(1, Math.round(size / 1024))} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function validationError(file) {
    if (file.type && !acceptedTypes.includes(file.type)) {
        return 'Formato não permitido. Use JPG, PNG ou WebP.';
    }

    if (file.size > maxFileSize) {
        return 'O arquivo excede o limite de 25 MB.';
    }

    return null;
}

function selectFiles(event) {
    if (processing.value) return;

    const files = Array.from(event.target.files ?? []);
    selectionError.value = '';

    if (files.length > maxFiles) {
        queue.value = [];
        selectionError.value = `Selecione no máximo ${maxFiles} imagens por envio.`;
        event.target.value = '';

        return;
    }

    queue.value = files.map((file, index) => {
        const error = validationError(file);

        return {
            id: `${file.name}-${file.size}-${file.lastModified}-${index}`,
            file,
            status: error ? 'failed' : 'waiting',
            error,
        };
    });
}

function submit() {
    if (processing.value || waitingCount.value === 0) return;

    processing.value = true;
    currentPosition.value = 0;
    uploadNext();
}

function uploadNext() {
    const index = queue.value.findIndex((item) => item.status === 'waiting');

    if (index === -1) {
        processing.value = false;
        currentPosition.value = 0;
        if (fileInput.value) fileInput.value.value = '';

        return;
    }

    const item = queue.value[index];
    item.status = 'uploading';
    item.error = null;
    currentPosition.value = sentCount.value + failedCount.value + 1;

    router.post(props.action, { file: item.file }, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        only: ['evidence', 'capabilities', 'flash'],
        onSuccess: () => {
            item.status = 'sent';
        },
        onError: (errors) => {
            item.status = 'failed';
            item.error = errors.file ?? Object.values(errors)[0] ?? 'Não foi possível enviar esta imagem.';
        },
        onFinish: () => uploadNext(),
    });
}
</script>

<template>
    <form class="rounded-2xl border border-dashed border-teal-300 bg-teal-50/60 p-4" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-950">Adicionar fotografia</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">Selecione até 10 imagens JPG, PNG ou WebP, com no máximo 25 MB cada.</p>
            </div>
            <button type="submit" :disabled="processing || waitingCount === 0" class="inline-flex min-h-10 items-center rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                {{ processing ? `Enviando ${currentPosition}/${queue.length}…` : `Enviar ${waitingCount} ${waitingCount === 1 ? 'foto' : 'fotos'}` }}
            </button>
        </div>
        <div class="mt-4">
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Arquivo</span>
                <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp" :disabled="processing" class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100" @change="selectFiles">
                <p v-if="selectionError" class="mt-1.5 text-xs font-medium text-rose-600">{{ selectionError }}</p>
            </label>
        </div>

        <div v-if="queue.length" class="mt-4 space-y-2" aria-live="polite">
            <div v-for="item in queue" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm">
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800">{{ item.file.name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ fileSize(item.file.size) }}</p>
                    <p v-if="item.error" class="mt-1 text-xs font-medium text-rose-600">{{ item.error }}</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClasses(item.status)">{{ statusLabel(item.status) }}</span>
            </div>
        </div>

        <p v-if="!processing && queue.length && (sentCount || failedCount)" class="mt-3 text-xs text-slate-600">
            {{ sentCount }} enviada(s) com sucesso<span v-if="failedCount">; {{ failedCount }} com falha</span>.
        </p>
    </form>
</template>
