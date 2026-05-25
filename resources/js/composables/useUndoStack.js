import { ref } from 'vue'

const MAX_SIZE = 50

export function useUndoStack() {
    const undoStack = ref([])
    const redoStack = ref([])

    // push(undoFn, redoFn?)
    // redoFn is optional — if omitted, undo clears the redo history for that operation.
    function push(undoFn, redoFn = null) {
        if (undoStack.value.length >= MAX_SIZE) undoStack.value.shift()
        undoStack.value.push({ undoFn, redoFn })
        redoStack.value = []   // new action always clears redo history
    }

    async function undo() {
        const item = undoStack.value.pop()
        if (!item) return
        await item.undoFn()
        if (item.redoFn) redoStack.value.push(item)
    }

    async function redo() {
        const item = redoStack.value.pop()
        if (!item) return
        await item.redoFn()
        undoStack.value.push(item)
    }

    function clear() {
        undoStack.value = []
        redoStack.value = []
    }

    return {
        push,
        undo,
        redo,
        clear,
        canUndo: () => undoStack.value.length > 0,
        canRedo: () => redoStack.value.length > 0,
    }
}
