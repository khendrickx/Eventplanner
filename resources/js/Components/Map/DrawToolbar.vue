<script setup>
import { ref } from 'vue'
import { elementTypesByDrawType } from '@/config/elementTypes.js'

const emit = defineEmits(['draw', 'cancel'])

const activeMode = ref(null)
const showMarkerPicker = ref(false)
const showZonePicker = ref(false)

function activate(mode, subtype = null) {
    activeMode.value = mode
    showMarkerPicker.value = false
    showZonePicker.value = false
    emit('draw', { mode, subtype })
}
</script>

<template>
    <div class="flex items-center gap-1">
        <!-- Route types -->
        <div class="relative">
            <select
                class="border rounded px-2 py-1.5 text-xs"
                @change="activate('route', $event.target.value)"
            >
                <option value="" disabled selected>Route…</option>
                <option
                    v-for="t in elementTypesByDrawType.route"
                    :key="t.id"
                    :value="t.id"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- Marker types -->
        <div class="relative">
            <select
                class="border rounded px-2 py-1.5 text-xs"
                @change="activate('marker', $event.target.value)"
            >
                <option value="" disabled selected>Marker…</option>
                <option
                    v-for="t in elementTypesByDrawType.marker"
                    :key="t.id"
                    :value="t.id"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- Zone types -->
        <div class="relative">
            <select
                class="border rounded px-2 py-1.5 text-xs"
                @change="activate('zone', $event.target.value)"
            >
                <option value="" disabled selected>Zone…</option>
                <option
                    v-for="t in elementTypesByDrawType.zone"
                    :key="t.id"
                    :value="t.id"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- Infrastructure types -->
        <div class="relative">
            <select
                class="border rounded px-2 py-1.5 text-xs"
                @change="activate('infrastructure', $event.target.value)"
            >
                <option value="" disabled selected>Infrastructure…</option>
                <option
                    v-for="t in elementTypesByDrawType.infrastructure"
                    :key="t.id"
                    :value="t.id"
                >{{ t.label }}</option>
            </select>
        </div>
    </div>
</template>
