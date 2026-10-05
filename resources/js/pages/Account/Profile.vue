<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    update_url: { type: String, required: true },
    remove_photo_url: { type: String, required: true },
    password_url: { type: String, required: true },
});

const form = useForm({ _method: 'put', name: props.profile.name, photo: null });
const fileInput = ref(null);
const previewUrl = ref(null);
const visiblePhotoUrl = computed(() => previewUrl.value ?? props.profile.photo_url);

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
}

function selectPhoto(event) {
    clearPreview();
    form.photo = event.target.files?.[0] ?? null;
    if (form.photo) previewUrl.value = URL.createObjectURL(form.photo);
}

function submit() {
    form.post(props.update_url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('photo');
            clearPreview();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function removePhoto() {
    if (!window.confirm('Remover sua foto de perfil?')) return;
    router.delete(props.remove_photo_url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('photo');
            clearPreview();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

onBeforeUnmount(clearPreview);
</script>

<template>
    <AppLayout title="Meu perfil" subtitle="Atualize seu nome e sua foto de perfil.">
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <div class="flex flex-wrap items-center gap-5">
                    <UserAvatar :name="form.name" :photo-url="visiblePhotoUrl" class="h-24 w-24 rounded-full bg-slate-800 text-2xl text-white" />
                    <div class="min-w-0 flex-1">
                        <label for="profile-photo" class="block text-sm font-semibold text-slate-700">Foto de perfil</label>
                        <input id="profile-photo" ref="fileInput" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm text-slate-600" @change="selectPhoto">
                        <p class="mt-2 text-xs text-slate-500">JPG, PNG ou WebP, até 2 MB. A foto será centralizada no círculo.</p>
                        <p v-if="form.errors.photo" class="mt-1 text-xs text-rose-600">{{ form.errors.photo }}</p>
                        <button v-if="profile.photo_url" type="button" class="mt-3 text-sm font-semibold text-rose-700 hover:text-rose-900" @click="removePhoto">Remover foto</button>
                    </div>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Nome *</span>
                    <input v-model="form.name" type="text" maxlength="150" autocomplete="name" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </label>

                <div>
                    <span class="text-sm font-semibold text-slate-700">E-mail de acesso</span>
                    <p class="mt-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">{{ profile.email }}</p>
                    <p class="mt-1 text-xs text-slate-500">O e-mail de acesso é gerenciado pela administração.</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                    <Link :href="password_url" class="text-sm font-semibold text-teal-700 hover:text-teal-900">Alterar senha</Link>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Salvar perfil</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
