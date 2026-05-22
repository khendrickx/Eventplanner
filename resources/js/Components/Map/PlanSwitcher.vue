<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    eventId: { type: Number, required: true },
    plans: { type: Array, required: true },
    activePlanId: { type: Number, required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['switch', 'created', 'renamed', 'deleted'])

const newPlanName = ref('')
const renamingId = ref(null)
const renameValue = ref('')

async function createPlan() {
    if (!newPlanName.value.trim()) return
    const { data } = await axios.post(`/api/events/${props.eventId}/plans`, { name: newPlanName.value })
    newPlanName.value = ''
    emit('created', data)
}

async function renamePlan(plan) {
    if (!renameValue.value.trim()) { renamingId.value = null; return }
    const { data } = await axios.patch(`/api/plans/${plan.id}`, { name: renameValue.value })
    renamingId.value = null
    emit('renamed', data)
}

async function duplicatePlan(plan) {
    const { data } = await axios.post(`/api/plans/${plan.id}/duplicate`)
    emit('created', data)
}

async function deletePlan(plan) {
    if (props.plans.length <= 1) return
    if (!confirm(`Delete plan "${plan.name}"?`)) return
    await axios.delete(`/api/plans/${plan.id}`)
    emit('deleted', plan.id)
}

function startRename(plan) {
    renamingId.value = plan.id
    renameValue.value = plan.name
}
</script>

<template>
    <div class="flex items-center gap-1">
        <template v-for="plan in plans" :key="plan.id">
            <div v-if="renamingId === plan.id" class="flex items-center gap-1">
                <input
                    v-model="renameValue"
                    class="border rounded px-2 py-1 text-xs w-28"
                    @keyup.enter="renamePlan(plan)"
                    @keyup.escape="renamingId = null"
                    autofocus
                />
                <button @click="renamePlan(plan)" class="text-xs text-blue-600">✓</button>
            </div>
            <button
                v-else
                @click="emit('switch', plan.id)"
                @dblclick="canEdit && startRename(plan)"
                :class="[
                    'px-3 py-1.5 rounded text-xs font-medium transition-colors',
                    activePlanId === plan.id
                        ? 'bg-black text-white'
                        : 'bg-white text-gray-700 border hover:bg-gray-50'
                ]"
                :title="canEdit ? 'Double-click to rename' : ''"
            >
                {{ plan.name }}
            </button>
            <button
                v-if="canEdit && plans.length > 1"
                @click="deletePlan(plan)"
                class="text-gray-400 hover:text-red-500 text-xs -ml-1"
                title="Delete plan"
            >×</button>
            <button
                v-if="canEdit"
                @click="duplicatePlan(plan)"
                class="text-gray-400 hover:text-gray-600 text-xs"
                title="Duplicate plan"
            >⎘</button>
        </template>

        <template v-if="canEdit">
            <input
                v-model="newPlanName"
                placeholder="New plan…"
                class="border rounded px-2 py-1 text-xs w-24"
                @keyup.enter="createPlan"
            />
            <button @click="createPlan" class="text-xs text-blue-600 border rounded px-2 py-1.5">+</button>
        </template>
    </div>
</template>
