<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    eventId: { type: Number, required: true },
    planId: { type: Number, required: true },
    overlays: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['added', 'updated', 'deleted'])

const uploading = ref(false)
const fileInput = ref(null)
const newOverlayName = ref('')

async function upload() {
    const file = fileInput.value?.files[0]
    if (!file || !newOverlayName.value.trim()) return

    uploading.value = true
    try {
        const form = new FormData()
        form.append('name', newOverlayName.value)
        form.append('image', file)
        // default bounds: a reasonable area around Brussels
        form.append('bounds[0][0]', '4.3')
        form.append('bounds[0][1]', '50.8')
        form.append('bounds[1][0]', '4.4')
        form.append('bounds[1][1]', '50.9')

        const { data } = await axios.post(`/api/plans/${props.planId}/overlays`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        newOverlayName.value = ''
        fileInput.value.value = ''
        emit('added', data)
    } finally {
        uploading.value = false
    }
}

async function updateOpacity(overlay, opacity) {
    const { data } = await axios.patch(`/api/overlays/${overlay.id}`, { opacity })
    emit('updated', data)
}

async function deleteOverlay(overlay) {
    if (!confirm(`Remove overlay "${overlay.name}"?`)) return
    await axios.delete(`/api/overlays/${overlay.id}`)
    emit('deleted', overlay.id)
}
</script>

<template>
    <div class="p-3 space-y-3 text-sm">
        <h3 class="font-medium text-xs text-gray-500 uppercase tracking-wide">Overlays</h3>

        <div v-for="overlay in overlays" :key="overlay.id" class="border rounded p-2 space-y-1">
            <div class="flex items-center justify-between">
                <span class="font-medium truncate">{{ overlay.name }}</span>
                <button v-if="canEdit" @click="deleteOverlay(overlay)" class="text-red-400 hover:text-red-600 text-xs">×</button>
            </div>
            <div v-if="canEdit" class="flex items-center gap-2">
                <label class="text-xs text-gray-500">Opacity</label>
                <input
                    type="range" min="0" max="1" step="0.05"
                    :value="overlay.opacity"
                    @input="updateOpacity(overlay, parseFloat($event.target.value))"
                    class="w-full"
                />
                <span class="text-xs w-8 text-right">{{ Math.round(overlay.opacity * 100) }}%</span>
            </div>
        </div>

        <div v-if="canEdit" class="space-y-1">
            <input v-model="newOverlayName" placeholder="Overlay name" class="w-full border rounded px-2 py-1 text-xs" />
            <input ref="fileInput" type="file" accept="image/*" class="w-full text-xs" />
            <button
                @click="upload"
                :disabled="uploading"
                class="w-full py-1.5 bg-black text-white rounded text-xs disabled:opacity-50"
            >
                {{ uploading ? 'Uploading…' : 'Upload Overlay' }}
            </button>
        </div>
    </div>
</template>
