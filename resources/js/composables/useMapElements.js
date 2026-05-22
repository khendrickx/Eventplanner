import { ref, toValue } from 'vue'
import axios from 'axios'

// planIdRef may be a ref<number> or a plain number — toValue() handles both
export function useMapElements(eventId, planIdRef) {
    const elements = ref([])
    const saving = ref(false)

    async function load() {
        const { data } = await axios.get(`/api/plans/${toValue(planIdRef)}/elements`)
        elements.value = data.data
    }

    async function create(payload, { forPlan = true } = {}) {
        saving.value = true
        try {
            const url = forPlan
                ? `/api/plans/${toValue(planIdRef)}/elements`
                : `/api/events/${eventId}/elements`
            const { data } = await axios.post(url, payload)
            elements.value.push(data)
            return data
        } finally {
            saving.value = false
        }
    }

    async function update(id, payload) {
        saving.value = true
        try {
            const { data } = await axios.patch(`/api/elements/${id}`, payload)
            const idx = elements.value.findIndex(e => e.id === id)
            if (idx !== -1) elements.value[idx] = data
            return data
        } finally {
            saving.value = false
        }
    }

    async function remove(id) {
        saving.value = true
        try {
            await axios.delete(`/api/elements/${id}`)
            elements.value = elements.value.filter(e => e.id !== id)
        } finally {
            saving.value = false
        }
    }

    return { elements, saving, load, create, update, remove }
}
