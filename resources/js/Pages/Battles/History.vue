<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { getRecentFights } from '@/api/gameApi';

const battles = ref([]);
const loading = ref(true);
const error = ref('');

const fetchBattles = async () => {
    loading.value = true;
    try {
        const data = await getRecentFights();
        battles.value = data;
    } catch (err) {
        error.value = 'Failed to load battle history.';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBattles();
});

const getStatusColor = (status) => {
    switch (status) {
        case 'finished': return 'text-green-600 bg-green-50';
        case 'active': return 'text-blue-600 bg-blue-50';
        case 'waiting': return 'text-orange-600 bg-orange-50';
        default: return 'text-gray-600 bg-gray-50';
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    try {
        return new Date(dateStr).toLocaleString();
    } catch (e) {
        return dateStr;
    }
}
</script>

<template>
    <Head title="Match History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Last Fights</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="loading" class="flex justify-center items-center py-10">
                            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-500"></div>
                        </div>

                        <div v-else-if="error" class="bg-red-50 text-red-700 p-4 rounded-md">
                            {{ error }}
                        </div>

                        <div v-else-if="battles.length === 0" class="text-center py-10 text-gray-500">
                            No recent battles found.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Participants</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="battle in battles" :key="battle.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{{ battle.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ battle.location_name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="name in battle.participants" :key="name" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span v-if="battle.winner_names.length" class="text-indigo-600 font-semibold">
                                                {{ battle.winner_names.join(', ') }} won
                                            </span>
                                            <span v-else-if="battle.status === 'finished'" class="italic">Draw/None</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getStatusColor(battle.status)]">
                                                {{ battle.status.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(battle.ended_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link 
                                                :href="route('fights.show_history', battle.id)"
                                                class="text-indigo-600 hover:text-indigo-900 font-semibold bg-indigo-50 px-3 py-1 rounded-md transition-colors"
                                            >
                                                View Log
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
