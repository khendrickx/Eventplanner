<script setup>
const props = defineProps({
    items:   { type: Array,   required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update'])

function addItem() {
    emit('update', [
        ...props.items,
        { id: crypto.randomUUID(), name: '', quantity: 1, unit: 'pcs', notes: '' },
    ])
}

function updateItem(id, field, value) {
    emit('update', props.items.map(item => item.id === id ? { ...item, [field]: value } : item))
}

function removeItem(id) {
    emit('update', props.items.filter(item => item.id !== id))
}
</script>

<template>
    <div class="space-y-1">
        <div v-for="item in items" :key="item.id" class="border rounded p-1.5 space-y-1 text-xs">
            <div class="flex gap-1">
                <input
                    :value="item.name"
                    :disabled="!canEdit"
                    @blur="updateItem(item.id, 'name', $event.target.value)"
                    placeholder="Item name"
                    class="flex-1 border rounded px-1.5 py-1 disabled:bg-gray-50"
                />
                <button
                    v-if="canEdit"
                    @click="removeItem(item.id)"
                    class="text-red-400 hover:text-red-600 px-1"
                    title="Remove"
                >×</button>
            </div>
            <div class="flex gap-1">
                <input
                    :value="item.quantity"
                    :disabled="!canEdit"
                    type="number" min="0"
                    @blur="updateItem(item.id, 'quantity', Number($event.target.value) || 0)"
                    class="w-16 border rounded px-1.5 py-1 disabled:bg-gray-50"
                    placeholder="Qty"
                />
                <input
                    :value="item.unit"
                    :disabled="!canEdit"
                    @blur="updateItem(item.id, 'unit', $event.target.value)"
                    class="w-16 border rounded px-1.5 py-1 disabled:bg-gray-50"
                    placeholder="Unit"
                />
                <input
                    :value="item.notes"
                    :disabled="!canEdit"
                    @blur="updateItem(item.id, 'notes', $event.target.value)"
                    class="flex-1 border rounded px-1.5 py-1 disabled:bg-gray-50"
                    placeholder="Notes"
                />
            </div>
        </div>

        <button
            v-if="canEdit"
            @click="addItem"
            class="w-full text-xs border border-dashed rounded px-2 py-1 text-gray-500 hover:text-gray-700 hover:border-gray-400"
        >+ Add item</button>

        <p v-if="items.length === 0 && !canEdit" class="text-xs text-gray-400 italic">No equipment listed</p>
    </div>
</template>
