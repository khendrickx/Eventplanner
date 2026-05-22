<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    event: Object,
})
</script>

<template>
    <Head :title="event.name" />
    <div class="h-screen flex flex-col">
        <div class="px-4 py-3 border-b flex items-center gap-4 bg-white">
            <span class="font-semibold">{{ event.name }}</span>
            <span class="text-xs text-gray-500 capitalize">{{ event.role }}</span>
        </div>
        <div class="flex-1 flex items-center justify-center text-gray-400">
            Map editor coming in Plan 2
        </div>
    </div>
</template>
