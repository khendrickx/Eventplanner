<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    description: '',
})
</script>

<template>
    <Head title="New Event" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">New Event</h2>
        </template>

        <div class="py-8 max-w-2xl mx-auto px-4">
            <form @submit.prevent="form.post(route('events.store'))">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Event name</label>
                    <input v-model="form.name" type="text" autofocus
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                    <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea v-model="form.description" rows="3"
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                </div>
                <button type="submit" :disabled="form.processing"
                        class="px-5 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50">
                    Create Event
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
