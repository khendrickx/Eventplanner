<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <GuestLayout>
        <Head title="Invitation Expired" />
        <div class="text-center">
            <h1 class="text-xl font-semibold mb-2">This invitation has expired</h1>
            <p class="text-gray-600">Please ask the event owner to send a new invitation.</p>
        </div>
    </GuestLayout>
</template>
