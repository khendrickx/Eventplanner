<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    event: Object,
    collaborators: Array,
    pendingInvitations: Array,
})

const form = useForm({
    name: props.event.name,
    description: props.event.description ?? '',
})

const inviteForm = useForm({
    email: '',
    role: 'editor',
})

function removeCollaborator(userId) {
    router.delete(route('events.collaborators.destroy', [props.event.id, userId]))
}

function updateRole(userId, role) {
    router.patch(route('events.collaborators.update', [props.event.id, userId]), { role })
}

function deleteEvent() {
    if (confirm('Delete this event? This cannot be undone.')) {
        router.delete(route('events.destroy', props.event.id))
    }
}
</script>

<template>
    <Head :title="`Settings — ${event.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">Event Settings</h2>
        </template>

        <div class="py-8 max-w-2xl mx-auto px-4 space-y-8">
            <!-- Event details -->
            <section>
                <h3 class="font-semibold mb-3">Details</h3>
                <form @submit.prevent="form.patch(route('events.update', event.id))">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input v-model="form.name" type="text"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                        <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea v-model="form.description" rows="3"
                                  class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-50">
                        Save Changes
                    </button>
                </form>
            </section>

            <!-- Collaborators -->
            <section>
                <h3 class="font-semibold mb-3">Collaborators</h3>

                <div v-if="collaborators.length > 0" class="border rounded-xl overflow-hidden mb-4">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="c in collaborators" :key="c.id" class="border-t first:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ c.name }}</div>
                                    <div class="text-gray-500">{{ c.email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <select :value="c.role" @change="updateRole(c.id, $event.target.value)"
                                            class="border rounded px-2 py-1 text-sm">
                                        <option value="editor">Editor</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="removeCollaborator(c.id)"
                                            class="text-red-500 hover:underline text-sm">Remove</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pendingInvitations.length > 0" class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Pending invitations:</p>
                    <ul class="text-sm space-y-1">
                        <li v-for="inv in pendingInvitations" :key="inv.id" class="text-gray-600">
                            {{ inv.email }} ({{ inv.role }}) — expires {{ inv.expires_at }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="inviteForm.post(route('events.collaborators.store', event.id), { onSuccess: () => inviteForm.reset() })">
                    <div class="flex gap-2">
                        <input v-model="inviteForm.email" type="email" placeholder="Email address"
                               class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black" />
                        <select v-model="inviteForm.role"
                                class="border rounded-lg px-3 py-2 text-sm">
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                        <button type="submit" :disabled="inviteForm.processing"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-50">
                            Invite
                        </button>
                    </div>
                    <p v-if="inviteForm.errors.email" class="text-red-500 text-sm mt-1">{{ inviteForm.errors.email }}</p>
                </form>
            </section>

            <!-- Danger zone -->
            <section>
                <h3 class="font-semibold mb-3 text-red-600">Danger Zone</h3>
                <button @click="deleteEvent"
                        class="px-4 py-2 border border-red-500 text-red-500 rounded-lg text-sm hover:bg-red-50">
                    Delete Event
                </button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
