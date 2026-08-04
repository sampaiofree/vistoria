<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    photos: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'Nenhuma evidência disponível.',
    },
});

const activePhoto = ref(null);
const closeButton = ref(null);
const trigger = ref(null);

const statusMeta = {
    ready: { label: 'Pronta', classes: 'bg-emerald-100 text-emerald-800' },
    pending: { label: 'Pendente', classes: 'bg-amber-100 text-amber-800' },
    processing: { label: 'Processando', classes: 'bg-sky-100 text-sky-800' },
    failed: { label: 'Falhou', classes: 'bg-rose-100 text-rose-800' },
};

const modalStatus = computed(() => photoStatus(activePhoto.value));

function photoStatus(photo) {
    return photo?.status ?? photo?.processing_status ?? 'ready';
}

function statusFor(photo) {
    return statusMeta[photoStatus(photo)] ?? statusMeta.pending;
}

function visualClass(photo, index = 0) {
    const rawVariant = photo?.visual_variant ?? photo?.placeholder_variant ?? ['concrete', 'structure', 'surface', 'repair'][index % 4];
    const variant = typeof rawVariant === 'number'
        ? ['concrete', 'structure', 'surface', 'repair'][(rawVariant - 1) % 4]
        : rawVariant;

    return `evidence-${variant}`;
}

function open(photo, event) {
    if (photoStatus(photo) !== 'ready') {
        return;
    }

    trigger.value = event?.currentTarget ?? null;
    activePhoto.value = photo;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeydown);
    nextTick(() => closeButton.value?.focus());
}

function close() {
    activePhoto.value = null;
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
    nextTick(() => trigger.value?.focus());
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        close();

        return;
    }

    if (event.key === 'Tab' && activePhoto.value) {
        event.preventDefault();
        closeButton.value?.focus();
    }
}

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div>
        <div v-if="photos.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
            <article v-for="(photo, index) in photos" :key="photo.id ?? `${photo.title}-${index}`" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="relative block aspect-[4/3] w-full overflow-hidden text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500 disabled:cursor-default"
                    :disabled="photoStatus(photo) !== 'ready'"
                    :aria-label="photoStatus(photo) === 'ready' ? `Ampliar ${photo.title}` : `${photo.title}: ${statusFor(photo).label}`"
                    @click="open(photo, $event)"
                >
                    <img v-if="photo.url" :src="photo.url" :alt="photo.caption || photo.title" class="h-full w-full object-cover">
                    <span v-else class="evidence-placeholder absolute inset-0" :class="visualClass(photo, index)" aria-hidden="true">
                        <span class="evidence-grid"></span>
                        <span class="evidence-marker">{{ String(photo.finding_sequence ?? index + 1).padStart(2, '0') }}</span>
                    </span>
                    <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="statusFor(photo).classes">
                            {{ photo.status_label ?? statusFor(photo).label }}
                        </span>
                        <span class="rounded-full bg-slate-950/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white">
                            {{ photo.role_label ?? photo.role ?? 'Foto' }}
                        </span>
                    </div>
                    <span class="absolute bottom-3 left-3 rounded-lg bg-slate-950/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white">
                        {{ photo.photo_interval || 'Imagem ilustrativa' }}
                    </span>
                </button>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-950">{{ photo.title }}</h3>
                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ photo.caption || 'Sem legenda.' }}</p>
                        </div>
                        <span v-if="photo.is_primary" class="rounded-full bg-teal-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-teal-700">Principal</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-500">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ photo.group_label || photo.finding_code || 'Ocorrência' }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ photo.photo_interval || '—' }}</span>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">{{ photo.location || photo.captured_at || photo.type_label || 'Evidência demonstrativa' }}</p>
                </div>
            </article>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
            {{ emptyMessage }}
        </div>

        <Teleport to="body">
            <div v-if="activePhoto" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/85 p-4" @mousedown.self="close">
                <section role="dialog" aria-modal="true" :aria-label="activePhoto.title" class="max-h-[94vh] w-full max-w-5xl overflow-auto rounded-3xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Imagem ilustrativa</p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-950">{{ activePhoto.title }}</h2>
                        </div>
                        <button ref="closeButton" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-xl text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500" aria-label="Fechar visualização" @click="close">×</button>
                    </div>
                    <div class="p-5">
                        <div class="relative aspect-video overflow-hidden rounded-2xl bg-slate-100">
                            <img v-if="activePhoto.url" :src="activePhoto.url" :alt="activePhoto.caption || activePhoto.title" class="h-full w-full object-contain">
                            <span v-else class="evidence-placeholder absolute inset-0" :class="visualClass(activePhoto)">
                                <span class="evidence-grid"></span>
                                <span class="evidence-marker evidence-marker-large">EVIDÊNCIA</span>
                            </span>
                            <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusMeta[modalStatus]?.classes">{{ activePhoto.status_label ?? statusMeta[modalStatus]?.label }}</span>
                                <span class="rounded-full bg-slate-950/80 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">{{ activePhoto.role_label ?? activePhoto.role ?? 'Foto' }}</span>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-500">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ activePhoto.group_label || 'Ocorrência' }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ activePhoto.photo_interval || '—' }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ activePhoto.location || '—' }}</span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">{{ activePhoto.caption }}</p>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.evidence-placeholder {
    display: block;
    background-color: #cbd5e1;
}

.evidence-concrete {
    background-image: radial-gradient(circle at 22% 30%, rgba(15, 23, 42, 0.22) 0 2px, transparent 3px), linear-gradient(135deg, #cbd5e1, #94a3b8 58%, #64748b);
}

.evidence-structure {
    background-image: linear-gradient(115deg, transparent 35%, rgba(15, 23, 42, 0.48) 36% 41%, transparent 42%), linear-gradient(30deg, #94a3b8, #475569);
}

.evidence-surface {
    background-image: repeating-linear-gradient(105deg, rgba(255,255,255,.08) 0 2px, transparent 2px 18px), linear-gradient(145deg, #64748b, #334155);
}

.evidence-repair {
    background-image: linear-gradient(90deg, transparent 47%, rgba(13,148,136,.78) 48% 52%, transparent 53%), linear-gradient(145deg, #cbd5e1, #64748b);
}

.evidence-grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px);
    background-size: 32px 32px;
}

.evidence-marker {
    position: absolute;
    right: 1rem;
    bottom: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 .75rem;
    border: 1px solid rgba(255,255,255,.55);
    border-radius: .75rem;
    background: rgba(15,23,42,.62);
    color: white;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .14em;
}

.evidence-marker-large {
    right: 1.5rem;
    bottom: 1.5rem;
}
</style>
