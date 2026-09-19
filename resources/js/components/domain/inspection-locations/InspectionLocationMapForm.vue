<script setup>
defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    cancelUrl: { type: String, required: true },
    editing: { type: Boolean, default: false },
});
defineEmits(['submit']);
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Título</span>
                <input v-model="form.title" required maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
            </label>
            <label v-if="!editing" class="block">
                <span class="text-sm font-semibold text-slate-700">Categoria</span>
                <select v-model="form.category" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="" disabled>Selecione</option>
                    <option v-for="category in categories" :key="category.code" :value="category.code">{{ category.code }} · {{ category.name }}</option>
                </select>
                <p v-if="form.errors.category" class="mt-1 text-xs text-rose-600">{{ form.errors.category }}</p>
            </label>
            <label v-if="editing" class="block" :class="{ 'md:col-span-2': !editing }">
                <span class="text-sm font-semibold text-slate-700">Ordem</span>
                <input v-model.number="form.position" type="number" min="1" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.position" class="mt-1 text-xs text-rose-600">{{ form.errors.position }}</p>
            </label>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Descrição</span>
                <textarea v-model="form.description" rows="4" maxlength="10000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
            </label>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <a :href="cancelUrl" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</a>
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Salvando…' : (editing ? 'Salvar mapa' : 'Criar mapa') }}</button>
        </div>
    </form>
</template>
