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

        <template v-else-if="name === 'classification'">
            <path d="M6 5.5h12v13H6z" />
            <path d="M9 9h6M9 12h6M9 15h3" />
        </template>

        <template v-else-if="name === 'map'">
            <path d="m4.5 6 5-2 5 2 5-2v14l-5 2-5-2-5 2Z" />
            <path d="M9.5 4v14M14.5 6v14" />
        </template>

        <template v-else-if="name === 'photos'">
            <rect x="4.5" y="5.5" width="15" height="13" rx="2" />
            <circle cx="9" cy="10" r="1.5" />
            <path d="m6.5 16 3.5-3 2.5 2 2.5-2 2.5 3" />
        </template>

        <template v-else-if="name === 'document'">
            <path d="M7 3.75h6.5L18 8.25V20H7Z" />
            <path d="M13.5 3.75v4.5H18M9.5 12h6M9.5 15.5h6" />
        </template>

        <template v-else-if="name === 'users'">
            <circle cx="9" cy="9" r="3" />
            <path d="M3.75 19a5.25 5.25 0 0 1 10.5 0M15.5 7a2.5 2.5 0 0 1 0 5M16 14a4.5 4.5 0 0 1 4.25 4.5" />
        </template>

        <template v-else-if="name === 'report'">
            <path d="M6 4.5h12v15H6Z" />
            <path d="M9 8h6M9 12h6M9 16h3" />
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

        <template v-else-if="name === 'settings'">
            <circle cx="12" cy="12" r="3.25" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.8 1.8-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.02 1.56V20h-2.55v-.1a1.7 1.7 0 0 0-1.02-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.8-1.8.06-.06A1.7 1.7 0 0 0 8.13 15a1.7 1.7 0 0 0-1.56-1.02H6.5v-2.55h.07A1.7 1.7 0 0 0 8.13 10a1.7 1.7 0 0 0-.34-1.88l-.06-.06 1.8-1.8.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.02-1.56V5h2.55v.1a1.7 1.7 0 0 0 1.02 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.8 1.8-.06.06A1.7 1.7 0 0 0 19.4 10a1.7 1.7 0 0 0 1.56 1.02H21v2.55h-.1A1.7 1.7 0 0 0 19.4 15Z" />
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

        <template v-else-if="name === 'chevron-down'">
            <path d="m6.5 9.5 5 5 5-5" />
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
