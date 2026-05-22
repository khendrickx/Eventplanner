<script setup>
import { Head } from '@inertiajs/vue3'
import MapEditor from '@/Components/Map/MapEditor.vue'

defineProps({
    event: Object,
    plans: Array,
})
</script>

<template>
    <Head :title="event.name" />
    <MapEditor :event="event" :initial-plans="plans" />
</template>
