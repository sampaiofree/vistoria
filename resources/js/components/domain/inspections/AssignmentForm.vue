<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ action: { type: String, required: true }, users: { type: Array, default: () => [] }, roles: { type: Array, default: () => [] } });
const form = useForm({ user_id: '', responsibility: '', is_primary: false });
function submit() { form.post(props.action, { preserveScroll: true, onSuccess: () => form.reset() }); }
</script>
<template>
    <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end" @submit.prevent="submit">
        <label class="text-sm font-medium text-slate-700">Responsável<select v-model="form.user_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="" disabled>Selecione</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
        <label class="text-sm font-medium text-slate-700">Função<select v-model="form.responsibility" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="" disabled>Selecione</option><option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option></select></label>
        <label class="flex items-center gap-2 pb-2 text-sm text-slate-700"><input v-model="form.is_primary" type="checkbox"> Principal</label>
        <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Atribuir</button>
    </form>
</template>
