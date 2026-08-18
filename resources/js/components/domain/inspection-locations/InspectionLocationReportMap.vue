<script setup>
import { computed } from 'vue';
import InspectionLocationMarkerOverlay from '@/components/domain/inspection-locations/InspectionLocationMarkerOverlay.vue';
import { layoutMarkerLegends } from '@/components/domain/inspection-locations/inspectionLocationMarkerPresentation.js';

const props = defineProps({
    map: { type: Object, required: true },
});

const viewWidth = 1000;
const viewHeight = computed(() => {
    const width = props.map.background?.width || 1200;
    const height = props.map.background?.height || 800;

    return viewWidth * (height / width);
});
const legendLayouts = computed(() => layoutMarkerLegends(props.map.markers || [], viewWidth, viewHeight.value));
</script>

<template>
    <div class="overflow-hidden bg-white">
        <svg class="block h-auto w-full" :viewBox="`0 0 ${viewWidth} ${viewHeight}`" role="img" :aria-label="`Mapa ${map.title}`">
            <image v-if="map.background?.url" :href="map.background.url" x="0" y="0" :width="viewWidth" :height="viewHeight" preserveAspectRatio="xMidYMid meet" />
            <template v-else>
                <rect x="0" y="0" :width="viewWidth" :height="viewHeight" fill="#e2e8f0" />
                <text x="500" :y="viewHeight / 2" text-anchor="middle" fill="#64748b" font-size="28">Mapa em processamento</text>
            </template>

            <InspectionLocationMarkerOverlay
                v-for="marker in (map.markers || [])"
                :key="marker.public_id"
                :marker="marker"
                :layout="legendLayouts[marker.public_id]"
                :view-width="viewWidth"
                :view-height="viewHeight"
            />
        </svg>
    </div>
</template>
