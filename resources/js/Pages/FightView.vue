<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, onUnmounted } from 'vue';
import FightLog from '@/Components/Fight/FightLog.vue';
import FightMap from '@/Components/Fight/FightMap.vue';
import BlockSelector from '@/Components/Fight/BlockSelector.vue';
import FightActionPanel from '@/Components/Fight/FightActionPanel.vue';
import { getFightState, submitActions, cancelFight } from '@/api/gameApi';

const props = defineProps({
    fightId: {
        type: Number,
        required: true
    }
});

const fightState = ref(null);
const page = usePage();
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const logRef = ref(null);
const actionPanelRef = ref(null);

const selectedTile = ref(null);
const selectedBlocks = ref([]);

let pollInterval = null;

const myCharacterId = computed(() => {
    return page.props.auth.user.id;
});

const myPosition = computed(() => {
    if (!fightState.value?.positions) return null;
    return fightState.value.positions.find(p => p.character_id === myCharacterId.value) || null;
});

const amICommitted = computed(() => {
    if (!fightState.value) return false;
    return fightState.value.actions_submitted.includes(myCharacterId.value);
});

const isFightActive = computed(() => {
    return fightState.value?.status === 'active';
});

const apAvailable = computed(() => 3);

const moveCost = computed(() => {
    if (!selectedTile.value) return 0;
    return 1 + selectedBlocks.value.length;
});

const apAvailableForQueue = computed(() => {
    return Math.max(0, apAvailable.value - moveCost.value);
});

const fetchState = async () => {
    try {
        const data = await getFightState(props.fightId);

        const oldRound = fightState.value?.round;
        fightState.value = data;

        if (oldRound !== undefined && oldRound !== data.round) {
            if (logRef.value) logRef.value.refresh();
            selectedTile.value = null;
            selectedBlocks.value = [];
            if (actionPanelRef.value) actionPanelRef.value.clearQueue();
        }

        if (data.status === 'active' && data.timer_remaining === 0) {
           startPolling(1000); 
        } else if (data.status === 'active') {
           startPolling(5000);
        } else {
           stopPolling();
        }

    } catch (e) {
        if (e.response && e.response.status === 403) {
            router.visit(route('game.index'));
        }
        error.value = 'Failed to load fight state.';
        console.error(e);
        stopPolling();
    } finally {
        loading.value = false;
    }
};

const handleQueueSubmit = async (queuedActions) => {
    const moveAction = selectedTile.value
        ? {
            type: 'move',
            target: { x: selectedTile.value.x, y: selectedTile.value.y },
            blocks: selectedBlocks.value
        }
        : null;

    const totalCost = queuedActions.length + (moveAction ? moveCost.value : 0);
    if (totalCost > apAvailable.value) {
        alert('Not enough Action Points for the selected actions.');
        return;
    }

    const payload = moveAction ? [...queuedActions, moveAction] : [...queuedActions];
    if (payload.length === 0) {
        alert('No actions selected.');
        return;
    }

    submitting.value = true;
    error.value = '';
    try {
        await submitActions(props.fightId, payload);
        await fetchState();

        if (actionPanelRef.value) actionPanelRef.value.clearQueue();
        selectedTile.value = null;
        selectedBlocks.value = [];

        setTimeout(() => {
            if (logRef.value) logRef.value.refresh();
        }, 500);

    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to submit actions.';
        alert(error.value);
    } finally {
        submitting.value = false;
    }
};

const handleCancelFight = async () => {
    try {
        await cancelFight(props.fightId);
        router.visit(route('game.index'));
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to cancel fight.';
        alert(error.value);
    }
};

const handleTileSelected = (tile) => {
    if (!myPosition.value) return;
    const dx = Math.abs(myPosition.value.x - tile.x);
    const dy = Math.abs(myPosition.value.y - tile.y);
    if (dx <= 1 && dy <= 1 && (dx + dy > 0)) {
        selectedTile.value = tile;
    }
};

const startPolling = (ms = 5000) => {
    stopPolling();
    pollInterval = setInterval(fetchState, ms);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onMounted(() => {
    fetchState();
});

onUnmounted(() => {
    stopPolling();
});

const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

</script>

<template>
    <Head :title="`Fight #${fightId}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Battlegrounds: Fight #{{ fightId }}
                </h2>
                <div v-if="fightState" class="flex gap-4 items-center">
                    <span class="px-3 py-1 bg-gray-200 rounded-full text-sm font-bold uppercase tracking-wider"
                          :class="{'bg-green-200 text-green-800': isFightActive, 'bg-yellow-200 text-yellow-800': fightState.status === 'waiting', 'bg-gray-300': fightState.status === 'finished'}">
                        {{ fightState.status }}
                    </span>
                    <span v-if="isFightActive" class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
                        Round {{ fightState.round }}
                    </span>
                    <span v-if="isFightActive" class="px-3 py-1 bg-blue-100 text-blue-800 font-mono font-bold rounded shadow-inner"
                          :class="{'text-red-600 bg-red-50 animate-pulse': fightState.timer_remaining <= 5}">
                        {{ formatTime(fightState.timer_remaining) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <div v-if="loading" class="text-center py-12 text-gray-500">
                    Connecting to the battlefield...
                </div>

                <div v-else-if="fightState" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 space-y-6">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div v-for="participant in fightState.participants" :key="participant.character_id" 
                                 class="bg-white rounded-lg p-4 shadow-sm border"
                                 :class="{'ring-2 ring-red-400': fightState.actions_submitted.includes(participant.character_id)}">
                                
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-bold text-lg">{{ participant.name }}</h3>
                                    <span v-if="fightState.actions_submitted.includes(participant.character_id)" 
                                          title="Actions Ready" class="text-green-500">OK</span>
                                    <span v-else title="Preparing Actions" class="text-yellow-500 opacity-50">...</span>
                                </div>
                                
                                <div class="w-full bg-gray-200 rounded-full h-4 mb-1 overflow-hidden">
                                    <div class="bg-red-600 h-4 transition-all duration-500" :style="`width: ${Math.max(0, Math.min(100, (participant.hp / participant.max_hp) * 100))}%`"></div>
                                </div>
                                <div class="text-right text-sm font-bold text-gray-700">
                                    {{ participant.hp }} / {{ participant.max_hp }} HP
                                </div>
                                <div class="text-xs text-gray-500 mt-2" v-if="participant.weapon">Weapon: {{ participant.weapon }}</div>
                            </div>
                            
                            <div v-if="fightState.participants.length < 2" class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-4 flex items-center justify-center text-gray-400">
                                Waiting for challenger...
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-4 shadow-sm border" v-if="fightState.map && fightState.positions">
                            <h3 class="font-bold text-lg mb-3">Tactical Map</h3>
                            <FightMap
                                :map="fightState.map"
                                :positions="fightState.positions"
                                :myCharacterId="myCharacterId"
                                :selectedTile="selectedTile"
                                @tileSelected="handleTileSelected"
                            />
                        </div>

                        <div v-if="fightState.status === 'waiting'" class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-6 rounded-lg text-center shadow-inner">
                            <div class="text-4xl mb-2">!</div>
                            <h3 class="font-bold text-lg">Waiting for opponent</h3>
                            <p>The battle will automatically begin when a second player joins.</p>
                            <button
                                @click="handleCancelFight"
                                class="mt-4 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded"
                            >
                                Cancel Fight
                            </button>
                        </div>
                        
                        <div v-else-if="fightState.status === 'finished'" class="bg-gray-100 border border-gray-300 text-gray-700 p-6 rounded-lg text-center shadow-inner">
                            <h3 class="font-bold text-lg mb-2">Battle Concluded</h3>
                            <a :href="route('game.index')" class="text-blue-600 hover:underline">Return to Lobby</a>
                        </div>
                        
                        <template v-else-if="isFightActive">
                            <div v-if="amICommitted" class="bg-green-50 border border-green-200 p-6 rounded-lg text-center shadow-inner">
                                <h3 class="font-bold text-green-800 mb-2">Actions Submitted!</h3>
                                <p class="text-green-700">Waiting for your opponent or the round timer...</p>
                                <button @click="fetchState" class="mt-4 text-sm text-green-600 hover:underline">Refresh Status</button>
                            </div>
                            
                            <div v-else class="space-y-6">
                                <div class="bg-white rounded-lg p-4 shadow-sm border">
                                    <h3 class="font-bold text-lg mb-3">Movement</h3>
                                    <div class="text-sm text-gray-600">Selected move</div>
                                    <div class="text-lg font-semibold mb-3">
                                        <span v-if="selectedTile">({{ selectedTile.x }}, {{ selectedTile.y }})</span>
                                        <span v-else class="text-gray-400">No tile selected</span>
                                    </div>

                                    <div class="text-sm text-gray-600 mb-2">Blocks during move (optional)</div>
                                    <BlockSelector :modelValue="selectedBlocks" @update:modelValue="(blocks) => selectedBlocks.value = blocks" />

                                    <div class="mt-3 text-sm text-gray-600 flex justify-between">
                                        <span>Move cost</span>
                                        <span>{{ moveCost }}</span>
                                    </div>
                                </div>

                                <FightActionPanel
                                    ref="actionPanelRef"
                                    :apAvailable="apAvailableForQueue"
                                    :disabled="submitting"
                                    @submitActions="handleQueueSubmit"
                                />
                            </div>
                        </template>

                    </div>

                    <div class="lg:col-span-1">
                        <FightLog ref="logRef" :fightId="fightId" />
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
