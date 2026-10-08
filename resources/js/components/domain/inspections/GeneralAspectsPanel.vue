<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';
import GeneralAspectsEditor from '@/components/domain/inspections/GeneralAspectsEditor.vue';
import { optimizeImageForUpload } from '@/lib/imageUploadOptimizer.js';

const props = defineProps({
    aspects: { type: Object, default: () => ({}) },
});

const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const cloneDocument = (document) => JSON.parse(JSON.stringify(document || emptyDocument()));

const editing = ref(false);
const selectedTemplatePublicId = ref('');
const pendingTemplate = ref(null);
const applyingTemplate = ref(false);
const templateError = ref('');
const templateSelect = ref(null);
const cancelTemplateConfirmationButton = ref(null);
let previousFocus = null;
const form = useForm({
    schema_version: 2,
    document: cloneDocument(props.aspects.document),
});

const canEdit = computed(() => props.aspects.can_edit === true && Boolean(props.aspects.update_url));
const templates = computed(() => Array.isArray(props.aspects.templates) ? props.aspects.templates : []);
const editor = ref(null);
const images = ref({});
const imageError = ref('');
const uploadCount = ref(0);
let pollTimer = null;
const referencedIds = computed(() => (form.document?.content || [])
    .filter((node) => node.type === 'image').map((node) => node.attrs?.assetId).filter(Boolean));
const imagesReady = computed(() => uploadCount.value === 0 && referencedIds.value.every((id) => images.value[id]?.status === 'ready'));

function syncImages() {
    images.value = Object.fromEntries(Object.entries(props.aspects.images || {}).map(([id, urls]) => [id, {
        status: 'ready', thumbnail: urls.thumbnail, optimized: urls.optimized,
    }]));
    imageError.value = '';
}

async function pollImages() {
    const pending = referencedIds.value.filter((id) => ['pending', 'processing'].includes(images.value[id]?.status));
    for (const id of pending) {
        try {
            const response = await fetch(images.value[id].statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Não foi possível consultar a imagem.');
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
    if (referencedIds.value.some((id) => ['pending', 'processing'].includes(images.value[id]?.status))) {
        pollTimer = window.setTimeout(pollImages, 2000);
    } else pollTimer = null;
}

async function selectImage(file) {
    imageError.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 25 * 1024 * 1024) {
        imageError.value = 'Use JPG, PNG ou WebP com até 25 MB.';
        return;
    }
    if (referencedIds.value.length >= 10) {
        imageError.value = 'Os Aspectos Gerais aceitam no máximo 10 imagens.';
        return;
    }
    uploadCount.value++;
    try {
        const optimized = await optimizeImageForUpload(file);
        const body = new FormData();
        body.append('file', optimized.file);
        const response = await fetch(props.aspects.upload_url, {
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

function syncForm() {
    form.defaults({
        schema_version: 2,
        document: cloneDocument(props.aspects.document),
    });
    form.reset();
    syncImages();
}

watch(() => props.aspects, syncForm, { deep: true });

function startEditing() {
    syncForm();
    selectedTemplatePublicId.value = '';
    pendingTemplate.value = null;
    templateError.value = '';
    editing.value = true;
}

function cancelEditing() {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
    editing.value = false;
    selectedTemplatePublicId.value = '';
    pendingTemplate.value = null;
    templateError.value = '';
    form.reset();
    form.clearErrors();
}

function applySelectedTemplate() {
    const template = templates.value.find(({ public_id }) => public_id === selectedTemplatePublicId.value);
    if (!template) return;

    selectedTemplatePublicId.value = '';
    templateError.value = '';
    if (template.application_error || !template.document) {
        templateError.value = template.application_error || 'Este modelo não pôde ser aplicado.';
        return;
    }
    pendingTemplate.value = template;
}

function closeTemplateConfirmation() {
    if (applyingTemplate.value) return;
    pendingTemplate.value = null;
}

async function confirmTemplateApplication() {
    if (!pendingTemplate.value) return;
    applyingTemplate.value = true;
    templateError.value = '';
    try {
        const url = props.aspects.apply_template_url.replace('__template__', encodeURIComponent(pendingTemplate.value.public_id));
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.errors?.template?.[0] || data.message || 'Não foi possível aplicar o modelo.');
        form.document = cloneDocument(data.document);
        images.value = { ...images.value, ...Object.fromEntries(Object.entries(data.images || {}).map(([id, urls]) => [id, {
            status: 'ready', thumbnail: urls.thumbnail, optimized: urls.optimized,
        }])) };
        applyingTemplate.value = false;
        closeTemplateConfirmation();
    } catch (error) {
        templateError.value = error.message || 'Não foi possível aplicar o modelo.';
        applyingTemplate.value = false;
        closeTemplateConfirmation();
    } finally {
        applyingTemplate.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') closeTemplateConfirmation();
}

watch(pendingTemplate, async (template) => {
    if (typeof document === 'undefined') return;

    if (template) {
        previousFocus = templateSelect.value ?? document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        cancelTemplateConfirmationButton.value?.focus();
        return;
    }

    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
    previousFocus?.focus?.();
    previousFocus = null;
});

onBeforeUnmount(() => {
    if (pollTimer) window.clearTimeout(pollTimer);
    if (typeof document === 'undefined') return;

    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

function submit() {
    if (!imagesReady.value) return;
    form.put(props.aspects.update_url, {
        preserveScroll: true,
        only: ['general_aspects', 'capabilities', 'flash'],
        onSuccess: () => {
            editing.value = false;
            selectedTemplatePublicId.value = '';
            pendingTemplate.value = null;
            form.clearErrors();
        },
    });
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Conteúdo do relatório</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Aspectos gerais do equipamento</h2>
                <p class="mt-1 text-sm text-slate-500">Este conteúdo será apresentado logo após a capa do relatório.</p>
            </div>
            <button
                v-if="canEdit && !editing"
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400"
                @click="startEditing"
            >
                Editar
            </button>
        </div>

        <form v-if="editing" class="mt-6 border-t border-slate-100 pt-5" @submit.prevent="submit">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Escolha um modelo para começar ou escreva o conteúdo manualmente.</p>
                <select
                    ref="templateSelect"
                    v-model="selectedTemplatePublicId"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!templates.length"
                    aria-label="Usar modelo de aspectos gerais"
                    @change="applySelectedTemplate"
                >
                    <option value="">Usar modelo…</option>
                    <option v-for="template in templates" :key="template.public_id" :value="template.public_id">
                        {{ template.name }}
                    </option>
                </select>
            </div>
            <p v-if="templateError" role="alert" class="mb-3 text-sm font-medium text-rose-700">{{ templateError }}</p>
            <GeneralAspectsEditor ref="editor" v-model="form.document" enable-media :image-assets="images" @image:selected="selectImage" @media:error="imageError = $event" />
            <p class="mt-2 text-xs font-medium text-rose-700">Antes de salvar, substitua os trechos em vermelho e retorne-os à cor padrão.</p>
            <p class="mt-2 text-xs text-slate-500">Até 100.000 caracteres e 10 imagens (JPG, PNG ou WebP, até 25 MB). Imagem isolada não substitui texto. Tabelas: até 6 colunas e 50 linhas, largura automática. Imagens ficam inteiras; tabelas continuam na página seguinte, com cabeçalho repetido.</p>
            <p v-if="!imagesReady && referencedIds.length" class="mt-2 text-xs text-amber-700">Aguarde o processamento das imagens antes de salvar. Remova ou substitua imagens com falha.</p>
            <p v-if="imageError" role="alert" class="mt-2 text-sm text-rose-600">{{ imageError }}</p>
            <p v-if="form.errors.document" class="mt-2 text-sm text-rose-600">{{ form.errors.document }}</p>
            <div class="mt-5 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="cancelEditing">Cancelar</button>
                <button type="submit" :disabled="form.processing || !imagesReady || applyingTemplate" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50">Salvar aspectos gerais</button>
            </div>
        </form>

        <div v-else class="mt-6 border-t border-slate-100 pt-5">
            <div v-if="aspects.has_content" class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-700">
                <GeneralAspectsDocument :document="aspects.document" :images="aspects.images" />
            </div>
            <p v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                Nenhum conteúdo informado. A seção não será incluída no relatório.
            </p>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="pendingTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="closeTemplateConfirmation">
            <section role="dialog" aria-modal="true" aria-labelledby="apply-general-aspects-template-title" aria-describedby="apply-general-aspects-template-description" class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex size-10 items-center justify-center rounded-full bg-amber-100 text-lg font-bold text-amber-700" aria-hidden="true">!</div>
                <p class="mt-4 text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Aspectos gerais</p>
                <h2 id="apply-general-aspects-template-title" class="mt-1 text-xl font-semibold text-slate-950">Usar modelo?</h2>
                <p class="mt-3 text-sm text-slate-600">
                    O modelo <strong class="text-slate-900">{{ pendingTemplate.name }}</strong> substituirá todo o conteúdo atual do editor.
                </p>
                <p id="apply-general-aspects-template-description" class="mt-2 text-sm text-slate-500">Você poderá editar o conteúdo antes de salvar os Aspectos Gerais.</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button ref="cancelTemplateConfirmationButton" type="button" :disabled="applyingTemplate" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="closeTemplateConfirmation">Cancelar</button>
                    <button type="button" :disabled="applyingTemplate" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50" @click="confirmTemplateApplication">{{ applyingTemplate ? 'Preparando modelo…' : 'Usar modelo' }}</button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
