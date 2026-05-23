<script setup>
import { computed, watch, ref } from 'vue'
import { elementTypesBySubtype, elementTypesByDrawType } from '@/config/elementTypes.js'
import EquipmentList from './EquipmentList.vue'
import * as turf from '@turf/turf'

const props = defineProps({
    element: { type: Object, default: null },
    plans:   { type: Array,  required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update'])

const local = ref(null)

watch(() => props.element, (el) => {
    local.value = el ? { ...el, properties: { ...(el.properties || {}) } } : null
}, { immediate: true })

function save(field, value) {
    emit('update', { id: local.value.id, [field]: value })
}

function saveProperty(key, value) {
    const updated = { ...(local.value.properties || {}), [key]: value }
    emit('update', { id: local.value.id, properties: updated })
}

const measurements = computed(() => {
    if (!local.value) return null
    const geo = local.value.geometry
    if (!geo) return null
    try {
        if (geo.type === 'LineString') {
            const len = turf.length({ type: 'Feature', geometry: geo }, { units: 'kilometers' })
            return { label: 'Length', value: len >= 1 ? `${len.toFixed(2)} km` : `${(len * 1000).toFixed(0)} m` }
        }
        if (geo.type === 'Polygon') {
            const area      = turf.area({ type: 'Feature', geometry: geo })
            const perimeter = turf.length({ type: 'Feature', geometry: geo }, { units: 'meters' })
            return [
                { label: 'Area',      value: area >= 10000 ? `${(area / 10000).toFixed(2)} ha` : `${area.toFixed(0)} m²` },
                { label: 'Perimeter', value: `${perimeter.toFixed(0)} m` },
            ]
        }
    } catch { /* ignore */ }
    return null
})

const subtypeOptions = computed(() => {
    if (!local.value) return []
    return elementTypesByDrawType[local.value.type] || []
})

const infraProperties = computed(() => {
    if (!local.value || local.value.type !== 'infrastructure') return []
    return elementTypesBySubtype[local.value.subtype]?.properties || []
})
</script>

<template>
    <div v-if="local" class="w-64 bg-white border-l p-4 overflow-y-auto shrink-0 text-sm space-y-4">

        <!-- Name -->
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
            <input
                :value="local.name || ''"
                :disabled="!canEdit"
                @blur="save('name', $event.target.value)"
                class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
                placeholder="Label (optional)"
            />
        </div>

        <!-- Subtype (hidden for groups — they have no meaningful subtype) -->
        <div v-if="local.type !== 'group'">
            <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
            <select
                :value="local.subtype"
                :disabled="!canEdit"
                @change="save('subtype', $event.target.value)"
                class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
            >
                <option value="">— freeform —</option>
                <option v-for="t in subtypeOptions" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
        </div>

        <!-- Plan assignment -->
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Plan</label>
            <select
                :value="local.event_plan_id ?? ''"
                :disabled="!canEdit"
                @change="save('event_plan_id', $event.target.value ? Number($event.target.value) : null)"
                class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
            >
                <option value="">All plans (shared)</option>
                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <!-- Level (only for child elements) -->
        <div v-if="local.parent_id != null">
            <label class="block text-xs font-medium text-gray-500 mb-1">Level</label>
            <input
                :value="local.properties?.level || ''"
                :disabled="!canEdit"
                @blur="saveProperty('level', $event.target.value)"
                class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
                placeholder="e.g. Ground floor, Floor 1"
            />
        </div>

        <!-- Infrastructure dimensions -->
        <template v-if="infraProperties.length > 0">
            <div v-for="prop in infraProperties" :key="prop.key">
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    {{ prop.key.charAt(0).toUpperCase() + prop.key.slice(1) }} ({{ prop.unit }})
                </label>
                <input
                    :value="local.properties?.[prop.key] || ''"
                    :disabled="!canEdit"
                    type="number"
                    @blur="saveProperty(prop.key, $event.target.value !== '' ? parseFloat($event.target.value) : null)"
                    class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
                />
            </div>
        </template>

        <!-- Measurements -->
        <div v-if="measurements">
            <label class="block text-xs font-medium text-gray-500 mb-1">Measurements</label>
            <template v-if="Array.isArray(measurements)">
                <p v-for="m in measurements" :key="m.label" class="text-xs text-gray-600">
                    {{ m.label }}: <span class="font-medium">{{ m.value }}</span>
                </p>
            </template>
            <p v-else class="text-xs text-gray-600">
                {{ measurements.label }}: <span class="font-medium">{{ measurements.value }}</span>
            </p>
        </div>

        <!-- Styling -->
        <div v-if="canEdit">
            <label class="block text-xs font-medium text-gray-500 mb-1">Colour</label>
            <div class="flex gap-2 items-center">
                <input
                    type="color"
                    :value="local.properties?.styling?.fill_color || '#3b82f6'"
                    @change="saveProperty('styling', { ...(local.properties?.styling || {}), fill_color: $event.target.value })"
                    class="w-8 h-8 rounded border cursor-pointer"
                />
                <input
                    type="color"
                    :value="local.properties?.styling?.stroke_color || '#1d4ed8'"
                    @change="saveProperty('styling', { ...(local.properties?.styling || {}), stroke_color: $event.target.value })"
                    class="w-8 h-8 rounded border cursor-pointer"
                    title="Stroke colour"
                />
                <label class="text-xs text-gray-500">
                    Opacity
                    <input
                        type="range" min="0" max="1" step="0.05"
                        :value="local.properties?.styling?.opacity ?? 1"
                        @change="saveProperty('styling', { ...(local.properties?.styling || {}), opacity: parseFloat($event.target.value) })"
                        class="w-20"
                    />
                </label>
            </div>
        </div>

        <!-- Notes -->
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
            <textarea
                :value="local.notes || ''"
                :disabled="!canEdit"
                @blur="save('notes', $event.target.value)"
                rows="3"
                class="w-full border rounded px-2 py-1.5 text-sm disabled:bg-gray-50"
                placeholder="Vendor info, comments…"
            />
        </div>

        <!-- Equipment -->
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Equipment</label>
            <EquipmentList
                :items="local.properties?.equipment || []"
                :can-edit="canEdit"
                @update="saveProperty('equipment', $event)"
            />
        </div>

        <!-- Lock / Hide -->
        <div v-if="canEdit" class="flex gap-4">
            <label class="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" :checked="local.is_locked" @change="save('is_locked', $event.target.checked)" />
                Locked
            </label>
            <label class="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" :checked="local.is_hidden" @change="save('is_hidden', $event.target.checked)" />
                Hidden
            </label>
        </div>

    </div>
</template>
