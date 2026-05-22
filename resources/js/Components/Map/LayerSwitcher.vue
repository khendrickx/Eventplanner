<script setup>
import { mapLayers } from '@/config/mapLayers.js'

const props = defineProps({
    activeLayerId: { type: String, required: true },
})
const emit = defineEmits(['change'])
</script>

<template>
    <div class="flex gap-1">
        <button
            v-for="layer in mapLayers"
            :key="layer.id"
            @click="emit('change', layer.id)"
            :class="[
                'px-3 py-1.5 rounded text-xs font-medium transition-colors',
                activeLayerId === layer.id
                    ? 'bg-black text-white'
                    : 'bg-white text-gray-700 border hover:bg-gray-50'
            ]"
        >
            {{ layer.label }}
        </button>
    </div>
</template>
