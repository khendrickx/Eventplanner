<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({
    events: Array,
})

function duplicate(id) {
    router.post(route('events.duplicate', id))
}
</script>

<template>
    <Head title="My Events" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">My Events</h2>
                <Link :href="route('events.create')"
                      class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    New Event
                </Link>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4">
            <div v-if="events.length === 0" class="text-center text-gray-500 py-16">
                No events yet. Create your first event to get started.
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="event in events" :key="event.id"
                     class="border rounded-xl p-5 hover:shadow-md transition-shadow bg-white">
                    <div class="flex items-start justify-between mb-2">
                        <Link :href="route('events.show', event.id)"
                              class="font-semibold text-lg hover:underline">
                            {{ event.name }}
                        </Link>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize ml-2 shrink-0">
                            {{ event.role }}
                        </span>
                    </div>
                    <p v-if="event.description" class="text-sm text-gray-500 mb-4 line-clamp-2">
                        {{ event.description }}
                    </p>
                    <div class="flex items-center gap-3 text-sm">
                        <Link :href="route('events.show', event.id)"
                              class="text-blue-600 hover:underline">Open</Link>
                        <Link v-if="event.role === 'owner'"
                              :href="route('events.edit', event.id)"
                              class="text-gray-500 hover:underline">Settings</Link>
                        <button @click="duplicate(event.id)"
                                class="text-gray-500 hover:underline">Duplicate</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
