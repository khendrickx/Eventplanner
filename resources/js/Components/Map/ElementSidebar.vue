<script setup>
import { computed } from 'vue'
import { elementTypesBySubtype } from '@/config/elementTypes.js'

const props = defineProps({
    elements: { type: Array, required: true },
    selectedId: { type: Number, default: null },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'toggle-lock', 'toggle-hide'])

const grouped = computed(() => {
    const groups = { route: [], marker: [], zone: [], infrastructure: [] }
    props.elements.forEach(el => groups[el.type]?.push(el))
    return groups
})

const groupLabels = { route: 'Routes', marker: 'Markers', zone: 'Zones', infrastructure: 'Infrastructure' }

function labelFor(el) {
    const typeDef = elementTypesBySubtype[el.subtype]
    return el.name || typeDef?.label || el.subtype || el.type
}
</script>

<template>
    <div class="w-56 border-r bg-white overflow-y-auto flex-shrink-0 text-sm">
        <template v-for="(items, type) in grouped" :key="type">
            <div v-if="items.length > 0">
                <div class="px-3 py-2 font-medium text-xs text-gray-500 uppercase tracking-wide bg-gray-50 border-b">
                    {{ groupLabels[type] }}
                </div>
                <button
                    v-for="el in items"
                    :key="el.id"
                    @click="emit('select', el.id)"
                    :class="[
                        'w-full text-left px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-50 transition-colors',
                        selectedId === el.id ? 'bg-blue-50 font-medium' : '',
                        el.is_hidden ? 'opacity-40' : '',
                    ]"
                >
                    <span class="truncate">{{ labelFor(el) }}</span>
                    <span v-if="canEdit" class="flex gap-1 shrink-0">
                        <button
                            @click.stop="emit('toggle-lock', el)"
                            :title="el.is_locked ? 'Unlock' : 'Lock'"
                            class="text-gray-400 hover:text-gray-700"
                        >{{ el.is_locked ? '🔒' : '🔓' }}</button>
                        <button
                            @click.stop="emit('toggle-hide', el)"
                            :title="el.is_hidden ? 'Show' : 'Hide'"
                            class="text-gray-400 hover:text-gray-700"
                        >{{ el.is_hidden ? '👁' : '🙈' }}</button>
                    </span>
                </button>
            </div>
        </template>
    </div>
</template>
