import { ref } from 'vue'

const MAX_SIZE = 50

export function useUndoStack() {
    const stack = ref([])

    function push(undoFn) {
        if (stack.value.length >= MAX_SIZE) {
            stack.value.shift()
        }
        stack.value.push(undoFn)
    }

    async function undo() {
        const fn = stack.value.pop()
        if (fn) await fn()
    }

    function clear() {
        stack.value = []
    }

    return { push, undo, clear, canUndo: () => stack.value.length > 0 }
}
