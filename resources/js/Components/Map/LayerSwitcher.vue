<script setup>
import { ref } from 'vue'
import { mapLayers } from '@/config/mapLayers.js'

const props = defineProps({
    activeLayerId: { type: String, required: true },
})
const emit = defineEmits(['change'])

const open = ref(false)

function select(id) {
    emit('change', id)
    open.value = false
}
</script>

<template>
    <!-- Floating layer-picker anchored to bottom-right of the map container -->
    <div class="absolute bottom-24 right-2 z-10 flex flex-col items-end gap-1">
        <!-- Layer thumbnail cards (visible when open) -->
        <div v-if="open" class="flex flex-col gap-1 items-end">
            <button
                v-for="layer in mapLayers"
                :key="layer.id"
                @click="select(layer.id)"
                :title="layer.label"
                :class="[
                    'flex items-center gap-2 bg-white rounded shadow px-2 py-1 text-xs font-medium transition-colors',
                    activeLayerId === layer.id
                        ? 'ring-2 ring-blue-500 text-blue-700'
                        : 'text-gray-700 hover:bg-gray-50'
                ]"
            >
                <img
                    v-if="layer.thumbnail"
                    :src="layer.thumbnail"
                    :alt="layer.label"
                    class="w-10 h-10 rounded object-cover shrink-0"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                />
                <div v-else class="w-10 h-10 rounded bg-gray-200 shrink-0" />
                <span class="whitespace-nowrap">{{ layer.label }}</span>
                <span v-if="activeLayerId === layer.id" class="text-blue-500">✓</span>
            </button>
        </div>

        <!-- Toggle button -->
        <button
            @click="open = !open"
            :title="open ? 'Close layer picker' : 'Switch map layer'"
            :class="[
                'w-10 h-10 rounded shadow flex items-center justify-center text-lg transition-colors',
                open ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
        >
            🗺
        </button>
    </div>
</template>
