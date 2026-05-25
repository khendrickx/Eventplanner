import { ref, toValue } from 'vue'
import axios from 'axios'

// planIdRef may be a ref<number|null> or a plain number/null — toValue() handles both.
// When planId is null (shared/global mode), load ALL event elements and create as shared.
export function useMapElements(eventId, planIdRef) {
    const elements = ref([])
    const saving = ref(false)

    async function load() {
        const planId = toValue(planIdRef)
        const url = planId !== null
            ? `/api/plans/${planId}/elements`
            : `/api/events/${eventId}/elements`
        const { data } = await axios.get(url)
        elements.value = data.data
    }

    async function create(payload, { forPlan = true } = {}) {
        saving.value = true
        try {
            const planId = toValue(planIdRef)
            const url = (forPlan && planId !== null)
                ? `/api/plans/${planId}/elements`
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
