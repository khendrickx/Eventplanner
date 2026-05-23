<script setup>
import EquipmentList from './EquipmentList.vue'

const props = defineProps({
    plan:    { type: Object,  required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update'])

function saveEquipment(items) {
    emit('update', { ...(props.plan.properties || {}), equipment: items })
}
</script>

<template>
    <div class="w-64 bg-white border-l p-4 overflow-y-auto shrink-0 text-sm space-y-4">
        <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Plan</p>
            <p class="font-medium">{{ plan.name }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Equipment</label>
            <EquipmentList
                :items="plan.properties?.equipment || []"
                :can-edit="canEdit"
                @update="saveEquipment"
            />
        </div>
    </div>
</template>
