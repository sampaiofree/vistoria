<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiIcon from './UiIcon.vue';

defineOptions({ name: 'AppSidebarItem' });

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    depth: {
        type: Number,
        default: 0,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate']);
const hasChildren = computed(() => (props.item.children || []).length > 0);
const open = ref(Boolean(props.item.default_open || props.item.active));

const depthClass = computed(() => {
    if (props.depth === 0) {
        return '';
    }

    return props.depth === 1 ? 'ml-3' : 'ml-5';
});

const rowClass = computed(() => {
    if (props.depth === 0) {
        return props.item.active
            ? 'bg-slate-700 text-white'
            : 'text-slate-300 hover:bg-slate-800 hover:text-white';
    }

    return props.item.active
        ? 'bg-slate-700 text-white'
        : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100';
});

const toneClass = computed(() => ({
    success: 'bg-emerald-400',
    warning: 'bg-amber-400',
    danger: 'bg-rose-400',
    neutral: 'bg-slate-500',
}[props.item.tone] ?? 'bg-slate-600'));

watch(
    () => props.item.active,
    (active) => {
        if (active) {
            open.value = true;
        }
    },
);

function toggle() {
    open.value = !open.value;
}
</script>

<template>
    <div :class="depthClass">
        <div
            class="group flex min-w-0 items-center rounded-md transition focus-within:ring-2 focus-within:ring-slate-400"
            :class="rowClass"
        >
            <Link
                v-if="item.href"
                :href="item.href"
                :title="collapsed ? item.label : undefined"
                :aria-current="item.active ? 'page' : undefined"
                class="flex min-w-0 flex-1 items-center gap-2.5 px-2.5 py-2.5 outline-none"
                @click="$emit('navigate')"
            >
                <span
                    v-if="depth === 0"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center transition"
                    :class="item.active ? 'text-white' : 'text-slate-400 group-hover:text-white'"
                >
                    <UiIcon :name="item.icon" class="h-4.5 w-4.5" />
                </span>
                <span v-else class="flex h-5 w-3 shrink-0 items-center justify-center">
                    <span class="h-1.5 w-1.5 rounded-full" :class="toneClass" />
                </span>

                <span class="min-w-0 flex-1" :class="collapsed ? 'lg:sr-only' : ''">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="truncate text-sm font-medium">{{ item.label }}</span>
                        <span
                            v-if="item.badge !== undefined && item.badge !== null"
                            class="ml-auto inline-flex min-w-5 shrink-0 items-center justify-center rounded bg-slate-600 px-1.5 py-0.5 text-[10px] font-semibold text-slate-200"
                        >
                            {{ item.badge }}
                        </span>
                    </span>
                    <span v-if="item.meta" class="mt-0.5 flex min-w-0 items-center gap-2 text-[11px] text-slate-500 group-hover:text-slate-400">
                        <span class="truncate">{{ item.meta }}</span>
                        <span v-if="item.secondary_badge !== undefined && item.secondary_badge !== null" class="ml-auto inline-flex shrink-0 items-center gap-1">
                            <UiIcon name="photos" class="h-3 w-3" />
                            {{ item.secondary_badge }}
                        </span>
                    </span>
                </span>
            </Link>

            <button
                v-else
                type="button"
                class="flex min-w-0 flex-1 items-center gap-2.5 px-2.5 py-2.5 text-left outline-none"
                :aria-expanded="hasChildren ? open : undefined"
                @click="toggle"
            >
                <span class="flex h-5 w-3 shrink-0 items-center justify-center">
                    <span class="h-1.5 w-1.5 rounded-full" :class="toneClass" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="truncate text-sm font-medium">{{ item.label }}</span>
                        <span
                            v-if="item.badge !== undefined && item.badge !== null"
                            class="ml-auto inline-flex min-w-5 shrink-0 items-center justify-center rounded bg-slate-600 px-1.5 py-0.5 text-[10px] font-semibold text-slate-200"
                        >
                            {{ item.badge }}
                        </span>
                    </span>
                    <span v-if="item.meta" class="mt-0.5 block truncate text-[11px] text-slate-500">{{ item.meta }}</span>
                </span>
            </button>

            <button
                v-if="hasChildren && item.href"
                type="button"
                class="mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-500 outline-none transition hover:bg-slate-600 hover:text-slate-200"
                :aria-label="`${open ? 'Recolher' : 'Expandir'} ${item.label}`"
                :aria-expanded="open"
                @click="toggle"
            >
                <UiIcon :name="open ? 'chevron-down' : 'chevron-right'" class="h-3.5 w-3.5" />
            </button>

            <UiIcon
                v-else-if="hasChildren"
                :name="open ? 'chevron-down' : 'chevron-right'"
                class="mr-3 h-3.5 w-3.5 shrink-0 text-slate-500"
            />
        </div>

        <div
            v-if="hasChildren && open && !collapsed"
            class="ml-3 mt-1 space-y-1 border-l border-slate-700 pl-1"
        >
            <AppSidebarItem
                v-for="child in item.children"
                :key="child.key || child.href || child.label"
                :item="child"
                :depth="depth + 1"
                @navigate="$emit('navigate')"
            />
        </div>
    </div>
</template>
