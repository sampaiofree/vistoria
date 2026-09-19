<script setup>
defineProps({ snapshot: { type: Object, required: true }, version: { type: [String, Number], default: 1 } });
const sections = ['organization', 'client', 'equipment'];
const assetFields = {"maintenance_plan_code":"Plano de manutenção","maintenance_item_code":"Item manutenção","area_code":"Area(usina)","area_name":"Area.nome","subarea_code":"Sub-area","subarea_name":"sub-area.nome","task_list_group":"GrpLisTar.","task_list_group_counter":"Numerador de grupos"};
const labels = { organization: 'Organização', client: 'Cliente', equipment: 'Equipamento' };
</script>
<template>
    <div>
        <div class="mb-3 flex items-center justify-between"><p class="text-sm text-slate-500">Registro imutável do contexto no momento da criação.</p><span class="text-xs text-slate-400">Versão {{ version }}</span></div>
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="section in sections" :key="section" class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ labels[section] }}</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ snapshot[section]?.tag || snapshot[section]?.name || '—' }}</dd>
                <dd v-if="snapshot[section]?.tag && snapshot[section]?.name" class="text-sm text-slate-500">{{ snapshot[section].name }}</dd>
                <template v-if="section === 'equipment'">
                    <dd v-for="(label, field) in assetFields" :key="field" class="text-sm text-slate-500">
                        <template v-if="snapshot.equipment && Object.hasOwn(snapshot.equipment, field)">{{ label }}: {{ snapshot.equipment[field] || '—' }}</template>
                    </dd>
                </template>
                <dd v-if="snapshot[section]?.code" class="text-sm text-slate-500">Código {{ snapshot[section].code }}</dd>
            </div>
        </dl>
    </div>
</template>
