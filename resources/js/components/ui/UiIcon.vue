<script setup>
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'dashboard'">
            <rect x="3.75" y="3.75" width="7" height="7" rx="1.4" />
            <rect x="13.25" y="3.75" width="7" height="10.5" rx="1.4" />
            <rect x="3.75" y="13.25" width="7" height="7" rx="1.4" />
            <rect x="13.25" y="16" width="7" height="4.25" rx="1.4" />
        </template>

        <template v-else-if="name === 'inspections'">
            <path d="M7 4.5h7.8L19 8.7V19.5a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 19.5V6A1.5 1.5 0 0 1 7 4.5Z" />
            <path d="M14.8 4.5V9H19" />
            <path d="M8 12h8" />
            <path d="M8 15.5h6" />
        </template>

        <template v-else-if="name === 'equipments'">
            <path d="M5.5 8h13l-1.5 8h-10z" />
            <path d="M8 8V6.5A1.5 1.5 0 0 1 9.5 5h5A1.5 1.5 0 0 1 16 6.5V8" />
            <path d="M8.5 16.5h7" />
            <path d="M9 11h6" />
        </template>

        <template v-else-if="name === 'clients'">
            <path d="M7.5 10.5a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" />
            <path d="M4.75 19.25a7.25 7.25 0 0 1 14.5 0" />
            <path d="M18.5 9.5a3 3 0 1 1 0 6" />
        </template>

        <template v-else-if="name === 'search'">
            <circle cx="10.25" cy="10.25" r="4.75" />
            <path d="m14 14 4.5 4.5" />
        </template>

        <template v-else-if="name === 'bell'">
            <path d="M9 17.25h6" />
            <path d="M7.5 16h9l-1.05-1.8A4.5 4.5 0 0 1 15 11.95V10a3 3 0 1 0-6 0v1.95c0 .8-.2 1.59-.45 2.25L7.5 16Z" />
        </template>

        <template v-else-if="name === 'user'">
            <circle cx="12" cy="8.5" r="3.25" />
            <path d="M5.75 20a6.25 6.25 0 0 1 12.5 0" />
        </template>

        <template v-else-if="name === 'menu'">
            <path d="M4.5 7h15" />
            <path d="M4.5 12h15" />
            <path d="M4.5 17h15" />
        </template>

        <template v-else-if="name === 'close'">
            <path d="M6 6l12 12" />
            <path d="M18 6 6 18" />
        </template>

        <template v-else-if="name === 'logout'">
            <path d="M10 5.5H6.5A1.5 1.5 0 0 0 5 7v10a1.5 1.5 0 0 0 1.5 1.5H10" />
            <path d="M13.5 8.5 17 12l-3.5 3.5" />
            <path d="M9 12h8" />
        </template>

        <template v-else-if="name === 'chevron-left'">
            <path d="m14.5 6.5-5 5 5 5" />
        </template>

        <template v-else-if="name === 'chevron-right'">
            <path d="m9.5 6.5 5 5-5 5" />
        </template>

        <template v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="7.5" />
            <path d="M12 8.5V12l2.5 1.5" />
        </template>

        <template v-else-if="name === 'review'">
            <path d="M5.75 7.25h12.5" />
            <path d="M5.75 12h8.5" />
            <path d="M5.75 16.75h5.5" />
            <path d="m17 11 2 2 3.25-3.25" />
        </template>

        <template v-else-if="name === 'correction'">
            <path d="M6 17.5 17.5 6" />
            <path d="m13.5 5.75 4.75 4.75" />
            <path d="M5.75 18.25 6.5 13l4.75-.75" />
        </template>

        <template v-else-if="name === 'approval'">
            <path d="M6 12.5 10 16.5 18 8.5" />
            <path d="M12 4.75a7.25 7.25 0 1 1 0 14.5 7.25 7.25 0 0 1 0-14.5Z" />
        </template>

        <template v-else-if="name === 'workflow'">
            <path d="M6 7h4.5" />
            <path d="M6 12h4.5" />
            <path d="M6 17h4.5" />
            <path d="M14.5 7h3.5" />
            <path d="M14.5 12h3.5" />
            <path d="M14.5 17h3.5" />
            <path d="M10.5 7v10" />
        </template>

        <template v-else-if="name === 'activity'">
            <path d="M6 12h3l1.5-4 3 8 1.5-4H18" />
            <path d="M12 4.75a7.25 7.25 0 1 1 0 14.5 7.25 7.25 0 0 1 0-14.5Z" />
        </template>

        <template v-else-if="name === 'arrow-right'">
            <path d="M5.75 12h12.5" />
            <path d="m13.5 7.75 4.75 4.25-4.75 4.25" />
        </template>

        <template v-else>
            <circle cx="12" cy="12" r="8" />
        </template>
    </svg>
</template>
