<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { getFightState } from '@/api/gameApi';
import FightLog from '@/Components/Fight/FightLog.vue';

const props = defineProps({
    fightId: {
        type: Number,
        required: true
    }
});

const battle = ref(null);
const loading = ref(true);
const error = ref('');

const fetchBattle = async () => {
    loading.value = true;
    try {
        const data = await getFightState(props.fightId);
        battle.value = data;
    } catch (err) {
        error.value = 'Failed to load battle record. It may be restricted or deleted.';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBattle();
});

const getParticipantName = (id) => {
    const p = battle.value?.participants.find(p => p.character_id === id);
    return p ? p.name : `Character #${id}`;
};
</script>

<template>
    <Head :title="`Battle #${fightId} Log`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Battle Record #{{ fightId }}
                </h2>
                <Link 
                    :href="route('fights.history')"
                    class="text-sm font-medium text-gray-500 hover:text-gray-700 underline"
                >
                    &larr; Back to History
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div v-if="loading" class="bg-white p-6 rounded-lg text-center py-10">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-500 mx-auto"></div>
                    <p class="mt-4 text-gray-500">Retrieving battle record...</p>
                </div>

                <div v-else-if="error" class="bg-red-50 border-l-4 border-red-400 p-4 rounded text-red-700">
                    {{ error }}
                    <div class="mt-2">
                        <Link :href="route('fights.history')" class="font-bold underline">Return to History</Link>
                    </div>
                </div>

                <template v-else-if="battle">
                    <!-- Battle Meta Info -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase">Match Info</h3>
                                <p class="mt-1 text-lg font-semibold text-gray-900">
                                    Status: <span class="capitalize">{{ battle.status }}</span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    Total Rounds: {{ battle.round }}
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase">Participants</h3>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <div 
                                        v-for="p in battle.participants" 
                                        :key="p.character_id"
                                        class="flex items-center space-x-2 bg-indigo-50 border border-indigo-100 px-3 py-1.5 rounded-lg"
                                    >
                                        <span class="text-indigo-700 font-bold font-mono text-xs">{{ p.team ?? '?' }}</span>
                                        <span class="text-gray-900 font-medium">{{ p.name }}</span>
                                        <span class="text-gray-400 text-xs">({{ p.hp }}/{{ p.max_hp }} HP)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- The Battle Log -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-6 border-b pb-4">Combat Log Feed</h3>
                            
                            <div class="max-h-[800px] overflow-y-auto pr-2 custom-scrollbar">
                                <FightLog 
                                    :fight-id="fightId"
                                    :fight-status="battle.status"
                                    :participants="battle.participants"
                                />
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
