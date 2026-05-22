<script setup>
import { elementTypesByDrawType } from '@/config/elementTypes.js'

const emit = defineEmits(['draw'])

function activate(event, mode) {
    const subtype = event.target.value
    event.target.value = ''
    emit('draw', { mode, subtype })
}
</script>

<template>
    <div class="flex items-center gap-1">
        <!-- Route types -->
        <div class="relative">
            <select
                class="border rounded px-2 py-1.5 text-xs"
                @change="activate($event, 'route')"
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
                @change="activate($event, 'marker')"
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
                @change="activate($event, 'zone')"
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
                @change="activate($event, 'infrastructure')"
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
