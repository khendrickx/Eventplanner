<script setup>
import { computed } from 'vue'
import { elementTypesBySubtype } from '@/config/elementTypes.js'

const props = defineProps({
    elements:         { type: Array,  required: true },
    selectedId:       { type: Number, default: null },
    canEdit:          { type: Boolean, default: false },
    expandedGroupIds: { type: Array,  required: true },
})

const emit = defineEmits(['select', 'toggle-lock', 'toggle-hide', 'toggle-group'])

const groups    = computed(() => props.elements.filter(el => el.type === 'group'))
const ungrouped = computed(() => props.elements.filter(el => el.type !== 'group' && el.parent_id == null))

function childrenOf(groupId) {
    return props.elements.filter(el => el.parent_id === groupId)
}

const ungroupedByType = computed(() => {
    const buckets = { route: [], marker: [], zone: [], infrastructure: [] }
    ungrouped.value.forEach(el => { if (buckets[el.type]) buckets[el.type].push(el) })
    return buckets
})

const typeLabels = { route: 'Routes', marker: 'Markers', zone: 'Zones', infrastructure: 'Infrastructure' }

function labelFor(el) {
    const typeDef = elementTypesBySubtype[el.subtype] || elementTypesBySubtype[el.type]
    return el.name || typeDef?.label || el.subtype || el.type
}
</script>

<template>
    <div class="w-56 border-r bg-white overflow-y-auto flex-shrink-0 text-sm">

        <!-- Groups section -->
        <template v-if="groups.length > 0">
            <div class="px-3 py-2 font-medium text-xs text-gray-500 uppercase tracking-wide bg-gray-50 border-b">
                Groups
            </div>
            <div v-for="group in groups" :key="group.id">
                <button
                    @click="emit('select', group.id)"
                    :class="[
                        'w-full text-left px-3 py-2 flex items-center gap-2 hover:bg-gray-50 transition-colors',
                        selectedId === group.id ? 'bg-blue-50 font-medium' : '',
                        group.is_hidden ? 'opacity-40' : '',
                    ]"
                >
                    <span
                        @click.stop="emit('toggle-group', group.id)"
                        class="text-gray-400 hover:text-gray-700 shrink-0 w-4 text-center"
                        :title="expandedGroupIds.includes(group.id) ? 'Collapse' : 'Expand'"
                    >{{ expandedGroupIds.includes(group.id) ? '▾' : '▸' }}</span>
                    <span class="truncate flex-1">{{ labelFor(group) }}</span>
                    <span v-if="canEdit" class="flex gap-1 shrink-0">
                        <button @click.stop="emit('toggle-lock', group)" class="text-gray-400 hover:text-gray-700" :title="group.is_locked ? 'Unlock' : 'Lock'">{{ group.is_locked ? '🔒' : '🔓' }}</button>
                        <button @click.stop="emit('toggle-hide', group)" class="text-gray-400 hover:text-gray-700" :title="group.is_hidden ? 'Show' : 'Hide'">{{ group.is_hidden ? '👁' : '🙈' }}</button>
                    </span>
                </button>

                <!-- Children, visible when group is expanded -->
                <template v-if="expandedGroupIds.includes(group.id)">
                    <button
                        v-for="child in childrenOf(group.id)"
                        :key="child.id"
                        @click="emit('select', child.id)"
                        :class="[
                            'w-full text-left pl-8 pr-3 py-1.5 flex items-center justify-between gap-2 hover:bg-gray-50 transition-colors border-l-2 border-indigo-200',
                            selectedId === child.id ? 'bg-blue-50 font-medium' : '',
                            child.is_hidden ? 'opacity-40' : '',
                        ]"
                    >
                        <span class="truncate text-xs">{{ labelFor(child) }}</span>
                        <span v-if="canEdit" class="flex gap-1 shrink-0">
                            <button @click.stop="emit('toggle-lock', child)" class="text-gray-400 hover:text-gray-700 text-xs" :title="child.is_locked ? 'Unlock' : 'Lock'">{{ child.is_locked ? '🔒' : '🔓' }}</button>
                            <button @click.stop="emit('toggle-hide', child)" class="text-gray-400 hover:text-gray-700 text-xs" :title="child.is_hidden ? 'Show' : 'Hide'">{{ child.is_hidden ? '👁' : '🙈' }}</button>
                        </span>
                    </button>
                </template>
            </div>
        </template>

        <!-- Ungrouped elements by type -->
        <template v-for="(items, type) in ungroupedByType" :key="type">
            <div v-if="items.length > 0">
                <div class="px-3 py-2 font-medium text-xs text-gray-500 uppercase tracking-wide bg-gray-50 border-b">
                    {{ typeLabels[type] }}
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
                        <button @click.stop="emit('toggle-lock', el)" class="text-gray-400 hover:text-gray-700" :title="el.is_locked ? 'Unlock' : 'Lock'">{{ el.is_locked ? '🔒' : '🔓' }}</button>
                        <button @click.stop="emit('toggle-hide', el)" class="text-gray-400 hover:text-gray-700" :title="el.is_hidden ? 'Show' : 'Hide'">{{ el.is_hidden ? '👁' : '🙈' }}</button>
                    </span>
                </button>
            </div>
        </template>

    </div>
</template>
