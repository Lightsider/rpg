<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { getGameState, getAvailableFights, createFight, joinFight, cancelFight } from '@/api/gameApi';

const gameState = ref(null);
const fights = ref([]);
const loading = ref(true);
const error = ref('');

const fetchGameData = async () => {
    loading.value = true;
    error.value = '';
    try {
        gameState.value = await getGameState();
        fights.value = await getAvailableFights();
    } catch (e) {
        if (e.response && e.response.status === 404 && e.response.data.error === "Character not found for this user.") {
             error.value = 'Character not found. (Character creation UI not implemented in this prototype)';
        } else {
             error.value = e.response?.data?.error || 'Failed to load game data.';
        }
    } finally {
        loading.value = false;
    }
};

const handleCreateFight = async () => {
    try {
        const response = await createFight();
        router.visit(route('fight.view', response.fight_id));
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to create fight');
    }
};

const handleJoinFight = async (fightId) => {
    try {
        await joinFight(fightId);
        router.visit(route('fight.view', fightId));
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to join fight');
    }
};

const handleCancelFight = async () => {
    if (!gameState.value?.currentFight) return;
    try {
        await cancelFight(gameState.value.currentFight.id);
        await fetchGameData();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to cancel fight');
    }
};

const openCurrentFight = () => {
    if (!gameState.value?.currentFight) return;
    router.visit(route('fight.view', gameState.value.currentFight.id));
};

onMounted(() => {
    fetchGameData();
});

</script>

<template>
    <Head title="Game Lobby" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Game Lobby
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <div v-if="loading" class="text-center text-gray-600">
                    Loading world data...
                </div>

                <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error:</strong>
                    <span class="block sm:inline"> {{ error }}</span>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Character Panel -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg md:col-span-1">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Your Character</h3>
                            <div class="space-y-4">
                                <div>
                                    <span class="text-gray-500 text-sm">Name</span>
                                    <div class="font-semibold text-xl">{{ gameState.character.name }}</div>
                                </div>
                                <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                                    <span class="font-medium">Health</span>
                                    <span class="text-green-600 font-bold">{{ gameState.character.hp }} / {{ gameState.character.max_hp }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 text-sm">Weapon</span>
                                    <div class="capitalize">{{ gameState.character.weapon.name }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location & Fights Panel -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg md:col-span-2">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-bold mb-2">Location: {{ gameState.location.name }}</h3>
                            <p class="text-gray-600 mb-6 italic">{{ gameState.location.description }}</p>

                            <div v-if="gameState.currentFight && gameState.currentFight.state === 'waiting'" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-yellow-800">You are waiting in Fight #{{ gameState.currentFight.id }}</div>
                                        <div class="text-sm text-yellow-700">Participants: {{ gameState.currentFight.participant_ids.length }} / 2</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openCurrentFight"
                                            class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-2 px-4 rounded"
                                        >
                                            Open Fight
                                        </button>
                                        <button
                                            @click="handleCancelFight"
                                            class="bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded"
                                        >
                                            Cancel Fight
                                        </button>
                                    </div>
                                </div>
                                <div class="text-xs text-yellow-700 mt-2">
                                    While waiting, you cannot create another fight or leave this location.
                                </div>
                            </div>

                            <div class="flex justify-between items-center border-b pb-2 mb-4">
                                <h4 class="font-semibold text-lg text-red-800">Available Fights</h4>
                                <button 
                                    @click="handleCreateFight"
                                    :disabled="!gameState.canCreateFight"
                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Create New Fight
                                </button>
                            </div>

                            <div v-if="fights.length === 0" class="text-center py-8 text-gray-500 bg-gray-50 rounded border border-dashed">
                                No active fights in this area. Create one to challenge others!
                            </div>

                            <ul v-else class="space-y-3">
                                <li v-for="fight in fights" :key="fight.id" class="flex items-center justify-between p-4 border rounded hover:border-red-300 transition-colors">
                                    <div>
                                        <div class="font-medium">Fight #{{ fight.id }}</div>
                                        <div class="text-sm text-gray-500">
                                            Status: <span class="uppercase font-semibold" :class="fight.state === 'waiting' ? 'text-yellow-600' : 'text-green-600'">{{ fight.state }}</span> -
                                            Participants: {{ fight.participants.length }} / 2
                                        </div>
                                    </div>
                                    <button 
                                        @click="handleJoinFight(fight.id)"
                                        :disabled="!gameState.canCreateFight || fight.state !== 'waiting' || fight.participants.length >= 2"
                                        class="bg-gray-800 hover:bg-gray-900 text-white py-1.5 px-4 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        Join Fight
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="mt-4 text-center">
                                <button @click="fetchGameData" class="text-sm text-blue-600 hover:underline">
                                    Refresh List
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

