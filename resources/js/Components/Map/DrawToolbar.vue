<script setup>
import { elementTypesByDrawType } from '@/config/elementTypes.js'

const emit = defineEmits(['draw'])

function activate(event, mode) {
    const val = event.target.value
    event.target.value = ''
    if (!val) return
    const subtype = val === '__custom__' ? null : val
    emit('draw', { mode, subtype })
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
            <option value="__custom__">Custom route&hellip;</option>
        </select>

        <select @change="e => activate(e, 'marker')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Marker</option>
            <option v-for="t in elementTypesByDrawType.marker" :key="t.id" :value="t.id">{{ t.label }}</option>
            <option value="__custom__">Custom marker&hellip;</option>
        </select>

        <select @change="e => activate(e, 'zone')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Zone</option>
            <option v-for="t in elementTypesByDrawType.zone" :key="t.id" :value="t.id">{{ t.label }}</option>
            <option value="__custom__">Custom zone&hellip;</option>
        </select>

        <select @change="e => activate(e, 'infrastructure')" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
            <option value="">+ Infra</option>
            <option v-for="t in elementTypesByDrawType.infrastructure" :key="t.id" :value="t.id">{{ t.label }}</option>
            <option value="__custom__">Custom infra&hellip;</option>
        </select>
    </div>
</template>
