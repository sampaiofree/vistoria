<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import GeneralAspectsEditor from '@/components/domain/inspections/GeneralAspectsEditor.vue';
import { optimizeImageForUpload } from '@/lib/imageUploadOptimizer.js';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    template: { type: Object, default: null },
    action: { type: String, required: true },
    method: { type: String, required: true },
    cancel_url: { type: String, required: true },
    equipment_fields: { type: Array, required: true },
    draft_token: { type: String, required: true },
    upload_url: { type: String, required: true },
});

const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const cloneDocument = (document) => JSON.parse(JSON.stringify(document || emptyDocument()));
const form = useForm({
    name: props.template?.name ?? '',
    schema_version: 2,
    document: cloneDocument(props.template?.document),
    draft_token: props.draft_token,
});
const editor = ref(null);
const images = ref({ ...(props.template?.images || {}) });
const imageError = ref('');
const uploadCount = ref(0);
let pollTimer = null;
const referencedIds = computed(() => (form.document?.content || [])
    .filter((node) => node.type === 'image').map((node) => node.attrs?.assetId).filter(Boolean));
const imagesReady = computed(() => uploadCount.value === 0 && referencedIds.value.every((id) => images.value[id]?.status === 'ready'));

async function pollImages() {
    const pending = referencedIds.value.filter((id) => ['pending', 'processing'].includes(images.value[id]?.status));
    for (const id of pending) {
        try {
            const response = await fetch(images.value[id].statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const data = await response.json();
            images.value = { ...images.value, [id]: {
                status: data.status, statusUrl: data.statusUrl,
                thumbnail: data.thumbnailUrl, optimized: data.optimizedUrl,
            } };
            if (data.status === 'failed') imageError.value = data.error || 'Falha no processamento da imagem.';
        } catch {
            imageError.value = 'Não foi possível consultar o processamento da imagem.';
        }
    }
    pollTimer = referencedIds.value.some((id) => ['pending', 'processing'].includes(images.value[id]?.status))
        ? window.setTimeout(pollImages, 2000) : null;
}

async function selectImage(file) {
    imageError.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 25 * 1024 * 1024) {
        imageError.value = 'Use JPG, PNG ou WebP com até 25 MB.';
        return;
    }
    if (referencedIds.value.length >= 10) {
        imageError.value = 'O modelo aceita no máximo 10 imagens.';
        return;
    }
    uploadCount.value++;
    try {
        const optimized = await optimizeImageForUpload(file);
        const body = new FormData();
        body.append('file', optimized.file);
        body.append('draft_token', props.draft_token);
        const response = await fetch(props.upload_url, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.errors?.file?.[0] || data.message || 'Falha no envio da imagem.');
        images.value = { ...images.value, [data.assetId]: {
            status: data.status, statusUrl: data.statusUrl,
            thumbnail: data.thumbnailUrl, optimized: data.optimizedUrl,
        } };
        editor.value?.insertImage(data.assetId);
        if (!pollTimer) pollTimer = window.setTimeout(pollImages, 1000);
    } catch (error) {
        imageError.value = error.message || 'Não foi possível enviar a imagem.';
    } finally {
        uploadCount.value--;
    }
}

onBeforeUnmount(() => { if (pollTimer) window.clearTimeout(pollTimer); });

function submit() {
    if (!imagesReady.value) return;
    form[props.method](props.action, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="title" :subtitle="subtitle">
        <section class="max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <label class="block max-w-2xl">
                    <span class="text-sm font-semibold text-slate-700">Nome do modelo *</span>
                    <input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Ex.: Equipamento em boas condições">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </label>

                <div>
                    <span class="text-sm font-semibold text-slate-700">Conteúdo *</span>
                    <div class="mt-1.5"><GeneralAspectsEditor ref="editor" v-model="form.document" :equipment-fields="equipment_fields" enable-media :image-assets="images" @image:selected="selectImage" @media:error="imageError = $event" /></div>
                    <p class="mt-2 text-xs font-medium text-rose-700">Use a cor vermelha para indicar os trechos que o inspetor deverá preencher antes de salvar.</p>
                    <p class="mt-2 text-xs text-slate-500">Até 100.000 caracteres e 10 imagens (JPG, PNG ou WebP, até 25 MB). Imagem isolada não substitui texto. Tabelas: até 6 colunas e 50 linhas, largura automática; continuam em outras páginas com cabeçalho repetido.</p>
                    <p v-if="!imagesReady && referencedIds.length" class="mt-2 text-xs text-amber-700">Aguarde as imagens ficarem prontas antes de salvar. Remova ou substitua imagens com falha.</p>
                    <p v-if="imageError" role="alert" class="mt-2 text-sm text-rose-600">{{ imageError }}</p>
                    <p v-if="form.errors.document" class="mt-1 text-xs text-rose-600">{{ form.errors.document }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <Link :href="cancel_url" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link>
                    <button type="submit" :disabled="form.processing || !imagesReady" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar modelo' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
