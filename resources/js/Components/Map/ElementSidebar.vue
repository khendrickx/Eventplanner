<script setup>
import { computed, ref, nextTick } from 'vue'
import { elementTypes, elementTypesBySubtype, elementCategories, SUBCATEGORY_LABELS } from '@/config/elementTypes.js'
import { iconDataUrl } from '@/config/elementIcons.js'

const props = defineProps({
    elements:         { type: Array,  required: true },
    selectedId:       { type: Number, default: null },
    canEdit:          { type: Boolean, default: false },
    expandedGroupIds: { type: Array,  required: true },
    plans:            { type: Array,  default: () => [] },
    activePlanId:     { default: null },
})

const emit = defineEmits([
    'select', 'toggle-lock', 'toggle-hide', 'toggle-group',
    'move-to-group', 'remove-from-group', 'create-group',
    'draw',
])

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref('objects')   // 'add' | 'objects'

function childrenOf(groupId) {
    return props.elements.filter(el => el.parent_id === groupId)
}

function labelFor(el) {
    const typeDef = elementTypesBySubtype[el.subtype] || elementTypesBySubtype[el.type]
    return el.name || typeDef?.label || el.subtype || el.type
}

function planBadgeFor(el) {
    if (props.activePlanId !== null) return null
    if (el.type === 'group') return null
    if (el.event_plan_id == null) return { label: 'Shared', cls: 'bg-indigo-100 text-indigo-700' }
    const plan = props.plans.find(p => p.id === el.event_plan_id)
    return plan ? { label: plan.name, cls: 'bg-gray-100 text-gray-600' } : null
}

// ── Add Objects panel ─────────────────────────────────────────────────────────
const addSearch = ref('')
const expandedCategories  = ref({})
const expandedSubcategories = ref({})

const filteredAddTypes = computed(() => {
    const q = addSearch.value.trim().toLowerCase()
    if (!q) return null
    return elementTypes.filter(t => t.type !== 'group' && t.label.toLowerCase().includes(q))
})

function toggleCategory(catId) {
    expandedCategories.value[catId] = !expandedCategories.value[catId]
}
function toggleSubcategory(subcatId) {
    expandedSubcategories.value[subcatId] = !expandedSubcategories.value[subcatId]
}
function selectType(t) {
    emit('draw', { mode: t.type, subtype: t.id })
    activeTab.value = 'objects'
}

// Pre-expand first category and its subcategories on mount
;(() => {
    if (elementCategories.length > 0) {
        const first = elementCategories[0]
        expandedCategories.value[first.id] = true
        if (first.subcategories.length > 0) {
            expandedSubcategories.value[first.subcategories[0].id] = true
        }
    }
})()

// ── Placed objects panel ──────────────────────────────────────────────────────
const search = ref('')

const placedCount = computed(() => props.elements.filter(el => el.type !== 'group').length)

const folders = computed(() => props.elements.filter(el => el.type === 'group'))

const ungrouped = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.elements.filter(el => {
        if (el.type === 'group' || el.parent_id != null) return false
        return !q || labelFor(el).toLowerCase().includes(q)
    })
})

const creatingFolder = ref(false)
const newFolderName  = ref('')
const folderInput    = ref(null)

async function startCreateFolder() {
    creatingFolder.value = true
    newFolderName.value  = ''
    await nextTick()
    folderInput.value?.focus()
}

function confirmCreateFolder() {
    const name = newFolderName.value.trim()
    if (name) emit('create-group', name)
    creatingFolder.value = false
}

function cancelCreateFolder() {
    creatingFolder.value = false
}

// ── Drag and drop ─────────────────────────────────────────────────────────────
const draggingEl = ref(null)
const dropTarget = ref(null)   // groupId (number) | 'ungrouped'

function onDragStart(el, e) {
    if (!props.canEdit || el.type === 'group') { e.preventDefault(); return }
    draggingEl.value = el
    e.dataTransfer.effectAllowed = 'move'
}
function onDragEnd() {
    draggingEl.value = null
    dropTarget.value = null
}
function onDragOverGroup(groupId, e) {
    if (!draggingEl.value || draggingEl.value.type === 'group') return
    e.preventDefault()
    dropTarget.value = groupId
}
function onDragOverUngrouped(e) {
    if (!draggingEl.value || draggingEl.value.parent_id == null) return
    e.preventDefault()
    dropTarget.value = 'ungrouped'
}
function onDropGroup(groupId) {
    if (draggingEl.value && draggingEl.value.id !== groupId && draggingEl.value.type !== 'group') {
        emit('move-to-group', draggingEl.value.id, groupId)
    }
    draggingEl.value = null
    dropTarget.value = null
}
function onDropUngrouped() {
    if (draggingEl.value && draggingEl.value.parent_id != null) {
        emit('remove-from-group', draggingEl.value.id)
    }
    draggingEl.value = null
    dropTarget.value = null
}
function onDragLeave() {
    dropTarget.value = null
}
</script>

<template>
    <div class="w-56 border-r bg-white flex flex-col flex-shrink-0 text-sm select-none">

        <!-- Tab headers -->
        <div class="flex border-b shrink-0">
            <button
                @click="activeTab = 'add'"
                :class="[
                    'flex-1 py-2 text-xs font-medium transition-colors',
                    activeTab === 'add'
                        ? 'text-blue-600 border-b-2 border-blue-600 bg-white'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50',
                ]"
            >Add Objects</button>
            <button
                @click="activeTab = 'objects'"
                :class="[
                    'flex-1 py-2 text-xs font-medium transition-colors',
                    activeTab === 'objects'
                        ? 'text-blue-600 border-b-2 border-blue-600 bg-white'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50',
                ]"
            >Placed Objects <span class="text-[10px] text-gray-400">({{ placedCount }})</span></button>
        </div>

        <!-- ─── Add Objects tab ───────────────────────────────────────────── -->
        <div v-if="activeTab === 'add'" class="overflow-y-auto flex-1">

            <!-- Search -->
            <div class="px-2 pt-2 pb-1">
                <input
                    v-model="addSearch"
                    type="search"
                    placeholder="Search types…"
                    class="w-full text-xs border rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400 bg-gray-50"
                />
            </div>

            <!-- Flat filtered results -->
            <template v-if="filteredAddTypes !== null">
                <button
                    v-for="t in filteredAddTypes"
                    :key="t.id"
                    @click="selectType(t)"
                    class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                    :title="`Draw ${t.label}`"
                >
                    <img :src="iconDataUrl(t.icon, t.defaultStyle?.color || '#6b7280', 20)" class="w-5 h-5 shrink-0" />
                    <div class="min-w-0">
                        <span class="truncate block">{{ t.label }}</span>
                        <span class="text-[10px] text-gray-400">{{ SUBCATEGORY_LABELS[t.subcategory] || t.subcategory }}</span>
                    </div>
                </button>
                <p v-if="filteredAddTypes.length === 0" class="px-3 py-2 text-xs text-gray-400 italic">No matches</p>
            </template>

            <!-- Hierarchical view when not searching -->
            <template v-else>
            <div v-for="cat in elementCategories" :key="cat.id">
                <!-- Category header -->
                <button
                    @click="toggleCategory(cat.id)"
                    class="w-full flex items-center gap-1.5 px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide hover:bg-gray-50 transition-colors"
                >
                    <span class="text-gray-400 text-[10px]">{{ expandedCategories[cat.id] ? '▾' : '▸' }}</span>
                    {{ cat.label }}
                </button>

                <template v-if="expandedCategories[cat.id]">
                    <div v-for="sub in cat.subcategories" :key="sub.id">
                        <!-- Subcategory header -->
                        <button
                            @click="toggleSubcategory(sub.id)"
                            class="w-full flex items-center gap-1.5 pl-5 pr-3 py-1.5 text-left text-[11px] font-medium text-gray-500 hover:bg-gray-50 transition-colors"
                        >
                            <span class="text-gray-300 text-[10px]">{{ expandedSubcategories[sub.id] ? '▾' : '▸' }}</span>
                            {{ sub.label }}
                        </button>

                        <!-- Element type items -->
                        <template v-if="expandedSubcategories[sub.id]">
                            <button
                                v-for="t in sub.types"
                                :key="t.id"
                                @click="selectType(t)"
                                class="w-full flex items-center gap-2 pl-8 pr-3 py-1.5 text-left text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors group"
                                :title="`Draw ${t.label}`"
                            >
                                <img
                                    :src="iconDataUrl(t.icon, t.defaultStyle?.color || '#6b7280', 20)"
                                    :alt="t.label"
                                    class="w-5 h-5 shrink-0"
                                />
                                <span class="truncate">{{ t.label }}</span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <!-- New folder button at bottom of Add Objects -->
            <div v-if="canEdit" class="px-3 py-3 border-t mt-2">
                <template v-if="!creatingFolder">
                    <button
                        @click="startCreateFolder"
                        class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1"
                    >
                        <span class="font-bold">+</span> New folder
                    </button>
                </template>
                <template v-else>
                    <input
                        ref="folderInput"
                        v-model="newFolderName"
                        class="w-full text-xs border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        placeholder="Folder name…"
                        @keydown.enter.prevent="confirmCreateFolder"
                        @keydown.escape="cancelCreateFolder"
                        @blur="confirmCreateFolder"
                    />
                </template>
            </div>
            </template>
        </div>

        <!-- ─── Placed Objects tab ────────────────────────────────────────── -->
        <div v-else class="overflow-y-auto flex-1">

            <!-- Search -->
            <div class="px-2 pt-2 pb-1">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search elements…"
                    class="w-full text-xs border rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400 bg-gray-50"
                />
            </div>

            <!-- New folder action -->
            <div v-if="canEdit" class="px-3 py-2 border-b">
                <template v-if="!creatingFolder">
                    <button
                        @click="startCreateFolder"
                        class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1"
                    >
                        <span class="font-bold">+</span> New folder
                    </button>
                </template>
                <template v-else>
                    <input
                        ref="folderInput"
                        v-model="newFolderName"
                        class="w-full text-xs border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        placeholder="Folder name…"
                        @keydown.enter.prevent="confirmCreateFolder"
                        @keydown.escape="cancelCreateFolder"
                        @blur="confirmCreateFolder"
                    />
                </template>
            </div>

            <!-- Folders -->
            <div v-for="folder in folders" :key="folder.id">

                <!-- Folder row (also a drop target) -->
                <div
                    @dragover="onDragOverGroup(folder.id, $event)"
                    @dragleave="onDragLeave"
                    @drop.prevent="onDropGroup(folder.id)"
                    :class="[
                        'flex items-center gap-1 px-2 py-1.5 hover:bg-gray-50 transition-colors',
                        folder.is_hidden ? 'opacity-40' : '',
                        dropTarget === folder.id ? 'ring-2 ring-inset ring-blue-400 bg-blue-50' : '',
                    ]"
                >
                    <button
                        @click.stop="emit('toggle-group', folder.id)"
                        class="text-gray-400 hover:text-gray-700 shrink-0 w-4 text-center leading-none"
                        :title="expandedGroupIds.includes(folder.id) ? 'Collapse' : 'Expand'"
                    >{{ expandedGroupIds.includes(folder.id) ? '▾' : '▸' }}</button>
                    <span class="text-gray-400 shrink-0 text-sm">📁</span>
                    <span
                        @click="emit('select', folder.id)"
                        class="truncate flex-1 cursor-pointer font-medium text-xs"
                        :class="selectedId === folder.id ? 'text-blue-600' : 'text-gray-700'"
                    >{{ folder.name || 'Unnamed folder' }}</span>
                    <span v-if="canEdit" class="flex gap-0.5 shrink-0">
                        <button @click.stop="emit('toggle-lock', folder)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="folder.is_locked ? 'Unlock all' : 'Lock all'">{{ folder.is_locked ? '🔒' : '🔓' }}</button>
                        <button @click.stop="emit('toggle-hide', folder)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="folder.is_hidden ? 'Show all' : 'Hide all'">{{ folder.is_hidden ? '👁' : '🙈' }}</button>
                    </span>
                </div>

                <!-- Children (visible when folder expanded) -->
                <template v-if="expandedGroupIds.includes(folder.id)">
                    <div
                        v-for="child in childrenOf(folder.id)"
                        :key="child.id"
                        :draggable="canEdit"
                        @dragstart="onDragStart(child, $event)"
                        @dragend="onDragEnd"
                        @click="emit('select', child.id)"
                        :class="[
                            'flex items-center gap-1.5 pl-7 pr-2 py-1.5 hover:bg-gray-50 transition-colors cursor-pointer border-l-2 border-indigo-200',
                            selectedId === child.id ? 'bg-blue-50' : '',
                            child.is_hidden ? 'opacity-40' : '',
                        ]"
                    >
                        <span v-if="canEdit" class="text-gray-300 shrink-0 cursor-grab">⠿</span>
                        <img
                            v-if="elementTypesBySubtype[child.subtype]?.icon"
                            :src="iconDataUrl(elementTypesBySubtype[child.subtype].icon, elementTypesBySubtype[child.subtype].defaultStyle?.color || '#6b7280', 16)"
                            class="w-4 h-4 shrink-0"
                        />
                        <span class="truncate flex-1 text-xs">{{ labelFor(child) }}</span>
                        <span v-if="planBadgeFor(child)" :class="['text-[10px] px-1 rounded shrink-0', planBadgeFor(child).cls]">{{ planBadgeFor(child).label }}</span>
                        <span v-if="canEdit" class="flex gap-0.5 shrink-0">
                            <button @click.stop="emit('toggle-lock', child)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="child.is_locked ? 'Unlock' : 'Lock'">{{ child.is_locked ? '🔒' : '🔓' }}</button>
                            <button @click.stop="emit('toggle-hide', child)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="child.is_hidden ? 'Show' : 'Hide'">{{ child.is_hidden ? '👁' : '🙈' }}</button>
                        </span>
                    </div>
                </template>
            </div>

            <!-- Ungrouped elements -->
            <div
                :class="[
                    'transition-colors min-h-4',
                    dropTarget === 'ungrouped' ? 'ring-2 ring-inset ring-blue-400 bg-blue-50' : '',
                ]"
                @dragover="onDragOverUngrouped"
                @dragleave="onDragLeave"
                @drop.prevent="onDropUngrouped"
            >
                <div
                    v-if="folders.length > 0 && (ungrouped.length > 0 || (draggingEl && draggingEl.parent_id != null))"
                    class="px-3 pt-2 pb-1 text-[10px] font-medium text-gray-400 uppercase tracking-wide border-t"
                >Elements</div>

                <div
                    v-for="el in ungrouped"
                    :key="el.id"
                    :draggable="canEdit"
                    @dragstart="onDragStart(el, $event)"
                    @dragend="onDragEnd"
                    @click="emit('select', el.id)"
                    :class="[
                        'flex items-center gap-1.5 px-2 py-1.5 hover:bg-gray-50 transition-colors cursor-pointer',
                        selectedId === el.id ? 'bg-blue-50' : '',
                        el.is_hidden ? 'opacity-40' : '',
                    ]"
                >
                    <span v-if="canEdit" class="text-gray-300 shrink-0 cursor-grab">⠿</span>
                    <img
                        v-if="elementTypesBySubtype[el.subtype]?.icon"
                        :src="iconDataUrl(elementTypesBySubtype[el.subtype].icon, elementTypesBySubtype[el.subtype].defaultStyle?.color || '#6b7280', 16)"
                        class="w-4 h-4 shrink-0"
                    />
                    <span class="truncate flex-1 text-xs">{{ labelFor(el) }}</span>
                    <span v-if="planBadgeFor(el)" :class="['text-[10px] px-1 rounded shrink-0', planBadgeFor(el).cls]">{{ planBadgeFor(el).label }}</span>
                    <span v-if="canEdit" class="flex gap-0.5 shrink-0">
                        <button @click.stop="emit('toggle-lock', el)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="el.is_locked ? 'Unlock' : 'Lock'">{{ el.is_locked ? '🔒' : '🔓' }}</button>
                        <button @click.stop="emit('toggle-hide', el)" class="text-gray-400 hover:text-gray-700 text-xs px-0.5" :title="el.is_hidden ? 'Show' : 'Hide'">{{ el.is_hidden ? '👁' : '🙈' }}</button>
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>
