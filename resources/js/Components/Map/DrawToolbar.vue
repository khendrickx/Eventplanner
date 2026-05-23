<script setup>
import { elementTypesByDrawType } from '@/config/elementTypes.js'

const emit = defineEmits(['draw'])

function activate(event, mode) {
    const subtype = event.target.value
    event.target.value = ''
    emit('draw', { mode, subtype: subtype || null })
}

function activateGroup() {
    emit('draw', { mode: 'group', subtype: null })
}
</script>

<template>
    <div class="flex items-center gap-1 shrink-0">
        <button
            @click="activateGroup"
            class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50"
            title="Draw a group boundary"
        >&#x25A1; Group</button>

        <select @change="e => activate(e, 'route')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Route</option>
            <option v-for="t in elementTypesByDrawType.route" :key="t.id" :value="t.id">{{ t.label }}</option>
        </select>

        <select @change="e => activate(e, 'marker')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Marker</option>
            <option v-for="t in elementTypesByDrawType.marker" :key="t.id" :value="t.id">{{ t.label }}</option>
        </select>

        <select @change="e => activate(e, 'zone')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Zone</option>
            <option v-for="t in elementTypesByDrawType.zone" :key="t.id" :value="t.id">{{ t.label }}</option>
        </select>

        <select @change="e => activate(e, 'infrastructure')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Infra</option>
            <option v-for="t in elementTypesByDrawType.infrastructure" :key="t.id" :value="t.id">{{ t.label }}</option>
        </select>
    </div>
</template>
