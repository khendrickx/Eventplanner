<script setup>
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    token: String,
    eventName: String,
})

const form = useForm({ password: '' })
</script>

<template>
    <Head :title="eventName + ' — Enter Password'" />
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-md p-8">
            <h1 class="text-xl font-semibold mb-1">{{ eventName }}</h1>
            <p class="text-sm text-gray-500 mb-6">This plan is password protected.</p>

            <form @submit.prevent="form.post(route('public.enter', token))">
                <label class="block text-sm font-medium mb-1">Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    autofocus
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black mb-1"
                    placeholder="Enter password"
                />
                <p v-if="form.errors.password" class="text-red-500 text-sm mb-3">{{ form.errors.password }}</p>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full mt-3 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-50"
                >
                    View Plan
                </button>
            </form>
        </div>
    </div>
</template>
