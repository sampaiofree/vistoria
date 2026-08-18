<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    action: { type: String, required: true },
    remove_logo_url: { type: String, required: true },
    remove_icon_url: { type: String, required: true },
});

const form = useForm({
    _method: 'put',
    name: props.organization.name ?? '',
    legal_name: props.organization.legal_name ?? '',
    document: props.organization.document ?? '',
    primary_color: props.organization.primary_color ?? '#0F172A',
    logo: null,
    icon: null,
});

function submit() {
    form.post(props.action, { forceFormData: true, preserveScroll: true });
}

function removeLogo() {
    if (!window.confirm('Remover o logotipo da empresa?')) return;
    router.delete(props.remove_logo_url, { preserveScroll: true });
}

function selectLogo(event) {
    form.logo = event.target.files?.[0] ?? null;
}

function removeIcon() {
    if (!window.confirm('Remover o ícone da empresa?')) return;
    router.delete(props.remove_icon_url, { preserveScroll: true });
}

function selectIcon(event) {
    form.icon = event.target.files?.[0] ?? null;
}

function selectPrimaryColor(event) {
    form.primary_color = event.target.value.toUpperCase();
}
</script>

<template>
    <AppLayout title="Empresa" subtitle="Dados cadastrais da organização responsável pela vistoria.">
        <section class="max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Identificação</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Dados da empresa</h2>
                    <p class="mt-1 text-sm text-slate-500">Essas informações serão usadas como identificação da organização.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-slate-700">Nome *</span>
                        <input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700">Razão social</span>
                        <input v-model="form.legal_name" type="text" maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.legal_name" class="mt-1 text-xs text-rose-600">{{ form.errors.legal_name }}</p>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700">CNPJ</span>
                        <input v-model="form.document" type="text" maxlength="18" inputmode="numeric" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="00.000.000/0000-00">
                        <p v-if="form.errors.document" class="mt-1 text-xs text-rose-600">{{ form.errors.document }}</p>
                    </label>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Identidade visual</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-950">Logotipo</h2>
                    <div class="mt-4 flex flex-wrap items-center gap-5">
                        <div class="flex h-28 w-28 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3">
                            <img v-if="organization.logo_url" :src="organization.logo_url" alt="Logotipo atual" class="max-h-full max-w-full object-contain">
                            <span v-else class="text-center text-xs text-slate-400">Nenhum logo</span>
                        </div>
                        <div>
                            <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block text-sm text-slate-600" @change="selectLogo">
                            <p class="mt-2 text-xs text-slate-500">JPG, PNG ou WebP, até 2 MB.</p>
                            <p v-if="form.errors.logo" class="mt-1 text-xs text-rose-600">{{ form.errors.logo }}</p>
                            <button v-if="organization.logo_url" type="button" class="mt-3 text-sm font-semibold text-rose-700 hover:text-rose-900" @click="removeLogo">Remover logotipo</button>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h2 class="text-lg font-semibold text-slate-950">Menu lateral</h2>
                    <p class="mt-1 text-sm text-slate-500">Personalize a cor e o ícone exibidos na navegação da empresa.</p>

                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        <div>
                            <span class="text-sm font-semibold text-slate-700">Cor primária *</span>
                            <div class="mt-1.5 flex items-center gap-3">
                                <input
                                    type="color"
                                    :value="form.primary_color"
                                    class="h-11 w-14 cursor-pointer rounded-md border border-slate-300 bg-white p-1"
                                    aria-label="Selecionar cor primária"
                                    @input="selectPrimaryColor"
                                >
                                <input
                                    v-model="form.primary_color"
                                    type="text"
                                    maxlength="7"
                                    pattern="#[0-9A-Fa-f]{6}"
                                    class="min-h-11 w-36 rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm uppercase"
                                    placeholder="#0F172A"
                                >
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Usada como fundo do menu lateral.</p>
                            <p v-if="form.errors.primary_color" class="mt-1 text-xs text-rose-600">{{ form.errors.primary_color }}</p>
                        </div>

                        <div>
                            <span class="text-sm font-semibold text-slate-700">Ícone da empresa</span>
                            <div class="mt-2 flex flex-wrap items-center gap-4">
                                <div class="flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-2">
                                    <img v-if="organization.icon_url" :src="organization.icon_url" alt="Ícone atual da empresa" class="max-h-full max-w-full object-contain">
                                    <span v-else class="text-center text-xs text-slate-400">Sem ícone</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block max-w-full text-sm text-slate-600" @change="selectIcon">
                                    <p class="mt-2 text-xs text-slate-500">JPG, PNG ou WebP, até 2 MB.</p>
                                    <p v-if="form.errors.icon" class="mt-1 text-xs text-rose-600">{{ form.errors.icon }}</p>
                                    <button v-if="organization.icon_url" type="button" class="mt-3 text-sm font-semibold text-rose-700 hover:text-rose-900" @click="removeIcon">Remover ícone</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 pt-5">
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar alterações' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
