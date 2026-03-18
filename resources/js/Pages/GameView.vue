<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, onUnmounted } from 'vue';
import FightLog from '@/Components/Fight/FightLog.vue';
import FightMap from '@/Components/Fight/FightMap.vue';
import BlockSelector from '@/Components/Fight/BlockSelector.vue';
import FightActionPanel from '@/Components/Fight/FightActionPanel.vue';
import CurrencyDisplay from '@/Components/CurrencyDisplay.vue';
import {
    getGameState,
    getAvailableFights,
    createFight,
    joinFight,
    cancelFight,
    getWeapons,
    getCharacterLoadout,
    updateCharacterLoadout,
    getFightState,
    submitActions,
} from '@/api/gameApi';

const page = usePage();
const gameState = ref(null);
const fights = ref([]);
const weapons = ref([]);
const loadout = ref({
    stats: { strength: 10, dexterity: 10, constitution: 10, wit: 10 },
    weapon_id: null,
    can_edit: true,
    blocked_reason: null,
});
const loadoutForm = ref({ strength: 10, dexterity: 10, constitution: 10, wit: 10, weapon_id: null });
const loadoutErrors = ref({});
const loadoutMessage = ref('');
const savingLoadout = ref(false);

const loading = ref(true);
const error = ref('');

// Battle State
const fightState = ref(null);
const submitting = ref(false);
const logRef = ref(null);
const actionPanelRef = ref(null);
const selectedTile = ref(null);
const selectedEnemy = ref(null);
const selectedBlocks = ref([]);
const actionMode = ref('none');
let countdownInterval = null;

const myCharacterId = computed(() => page.props.auth.user.id);
const myPosition = computed(() => {
    if (!fightState.value?.positions) return null;
    return fightState.value.positions.find(p => p.character_id === myCharacterId.value) || null;
});
const amICommitted = computed(() => {
    if (!fightState.value?.actions_submitted) return false;
    return fightState.value.actions_submitted.includes(myCharacterId.value);
});
const isFightActive = computed(() => fightState.value?.status === 'active');
const apAvailable = computed(() => 3);
const moveCost = computed(() => (selectedTile.value ? 1 + selectedBlocks.value.length : 0));
const apAvailableForQueue = computed(() => (actionMode.value === 'move' ? Math.max(0, apAvailable.value - moveCost.value) : apAvailable.value));

const positionsMap = computed(() => {
    const map = new Map();
    if (fightState.value?.positions) {
        fightState.value.positions.forEach(pos => map.set(`${pos.x},${pos.y}`, pos.character_id));
    }
    return map;
});

const getCharacterAt = (tile) => tile ? (positionsMap.value.get(`${tile.x},${tile.y}`) || null) : null;
const isAdjacentToMe = (tile) => {
    if (!myPosition.value || !tile) return false;
    const dx = Math.abs(myPosition.value.x - tile.x);
    const dy = Math.abs(myPosition.value.y - tile.y);
    return dx <= 1 && dy <= 1 && (dx + dy > 0);
};

const fetchGameData = async () => {
    loading.value = true;
    error.value = '';
    try {
        gameState.value = await getGameState();
        fights.value = await getAvailableFights();
        
        // If there's an active or waiting fight, fetch its state
        if (gameState.value.currentFight) {
            await fetchFightState(gameState.value.currentFight.id);
        } else {
            fightState.value = null;
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to load game data.';
    } finally {
        loading.value = false;
    }
};

const fetchFightState = async (id) => {
    try {
        const data = await getFightState(id);
        fightState.value = data;
        startLocalTimer();
        initWebSocket(id);
    } catch (e) {
        console.error("Failed to fetch fight state", e);
    }
};

let battleChannel = null;

const initWebSocket = (battleId) => {
    // Cleanup existing channel if any
    if (battleChannel) {
        window.Echo.leave(`battle.${battleChannel}`);
    }
    battleChannel = battleId;

    const onUpdate = (payload) => {
        console.log('[Echo] Battle Update:', payload);
        if (fightState.value) {
            fightState.value.round = payload.round;
            payload.players.forEach(p => {
                const existing = fightState.value.participants.find(part => part.character_id === p.character_id);
                if (existing) existing.hp = p.hp;
                if (p.character_id === myCharacterId.value && gameState.value?.character) {
                    gameState.value.character.hp = p.hp;
                }
            });
            fightState.value.positions = payload.players.map(p => ({ character_id: p.character_id, x: p.x, y: p.y }));
        }
        if (logRef.value && payload.events) {
            logRef.value.pushRound(payload.round, payload.events);
        }
    };

    const onRoundStarted = (payload) => {
        console.log('[Echo] Round Started:', payload);
        if (fightState.value) {
            Object.assign(fightState.value, {
                round: payload.round,
                timer_remaining: payload.timeout,
                status: 'active',
                actions_submitted: []
            });
        }
        clearSelection();
        if (actionPanelRef.value) actionPanelRef.value.clearQueue();
    };

    const onBattleEnded = (payload) => {
        console.log('[Echo] Battle Ended:', payload);
        if (fightState.value) fightState.value.status = 'finished';
        stopLocalTimer();
    };

    const onBattleJoined = (payload) => {
        console.log('[Echo] Battle Joined:', payload);
        // Ensure all required fields exist to prevent UI crashes
        if (!payload.actions_submitted) payload.actions_submitted = [];
        if (!payload.id && payload.battleId) payload.id = payload.battleId;
        
        fightState.value = payload;
        startLocalTimer();
    };

    const onCommitted = (payload) => {
        console.log('[Echo] Battle Committed:', payload);
        if (fightState.value) {
            fightState.value.actions_submitted = payload.committed_character_ids;
        }
    };

    window.Echo.private(`battle.${battleId}`)
        .listen('.battle.joined', onBattleJoined)
        .listen('.round.started', onRoundStarted)
        .listen('.battle.updated', onUpdate)
        .listen('.battle.ended', onBattleEnded)
        .listen('.battle.committed', onCommitted);
};

const initCharacterWebSocket = () => {
    const charId = myCharacterId.value;
    window.Echo.private(`character.${charId}`)
        .listen('.character.currency', (payload) => {
            console.log('[Echo] Currency Update:', payload);
            if (gameState.value?.character && gameState.value.character.id === payload.characterId) {
                gameState.value.character.currency_copper = payload.copper;
            }
        });
};

let locationChannel = null;

const initLocationWebSocket = (locationId) => {
    if (locationChannel === locationId) return;
    if (locationChannel) {
        window.Echo.leave(`location.${locationChannel}`);
    }
    locationChannel = locationId;

    window.Echo.private(`location.${locationId}`)
        .listen('.battle.created', (payload) => {
            console.log('[Echo] Battle Created:', payload);
            const exists = fights.value.find(f => f.id === payload.battle.id);
            if (!exists) {
                fights.value.push(payload.battle);
            }
        })
        .listen('.battle.removed', (payload) => {
            console.log('[Echo] Battle Removed:', payload);
            fights.value = fights.value.filter(f => f.id !== payload.battleId);
        });
};


const handleQueueSubmit = async (queuedActions) => {
    if (queuedActions.filter(a => a.type === 'attack').length > 2) return alert('Max 2 attacks.');
    
    const moveAction = actionMode.value === 'move' && selectedTile.value 
        ? { type: 'move', target: { x: selectedTile.value.x, y: selectedTile.value.y }, blocks: selectedBlocks.value }
        : null;

    if (queuedActions.length + (moveAction ? moveCost.value : 0) > apAvailable.value) return alert('Not enough AP.');
    
    const payload = moveAction ? [...queuedActions, moveAction] : [...queuedActions];
    if (!payload.length) return alert('No actions.');

    submitting.value = true;
    try {
        await submitActions(fightState.value.id, payload);
        await fetchFightState(fightState.value.id);
        if (actionPanelRef.value) actionPanelRef.value.clearQueue();
        clearSelection();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to submit.');
    } finally {
        submitting.value = false;
    }
};

const handleCreateFight = async () => {
    try {
        const response = await createFight();
        await fetchGameData();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to create fight');
    }
};

const handleJoinFight = async (id) => {
    try {
        await joinFight(id);
        await fetchGameData();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to join fight');
    }
};

const handleCancelFight = async () => {
    const id = gameState.value?.currentFight?.id || fightState.value?.id;
    if (!id) return;
    try {
        await cancelFight(id);
        fightState.value = null;
        await fetchGameData();
        await fetchLoadoutData();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to cancel fight');
    }
};

const clearSelection = () => {
    selectedTile.value = selectedEnemy.value = null;
    selectedBlocks.value = [];
    actionMode.value = 'none';
};

const handleTileSelected = (tile) => {
    if (!myPosition.value) return;
    if ((selectedTile.value?.x === tile.x && selectedTile.value?.y === tile.y) || 
        (selectedEnemy.value?.x === tile.x && selectedEnemy.value?.y === tile.y)) return clearSelection();
    
    if (!isAdjacentToMe(tile)) return;
    const cid = getCharacterAt(tile);
    if (cid && cid !== myCharacterId.value) {
        selectedEnemy.value = tile;
        selectedTile.value = null;
        actionMode.value = 'attack';
    } else if (!cid) {
        selectedTile.value = tile;
        selectedEnemy.value = null;
        actionMode.value = 'move';
    }
};

const startLocalTimer = () => {
    stopLocalTimer();
    countdownInterval = setInterval(() => {
        if (fightState.value?.timer_remaining > 0 && fightState.value?.status === 'active') {
            fightState.value.timer_remaining--;
        }
    }, 1000);
};

const stopLocalTimer = () => {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = null;
};

const formatTime = (s) => `${Math.floor(s / 60)}:${(s % 60).toString().padStart(2, '0')}`;

const fetchLoadoutData = async () => {
    try {
        const [weaponList, loadoutData] = await Promise.all([getWeapons(), getCharacterLoadout()]);
        weapons.value = weaponList;
        loadout.value = loadoutData;
        Object.assign(loadoutForm.value, {
            strength: loadoutData.stats.strength,
            dexterity: loadoutData.stats.dexterity,
            constitution: loadoutData.stats.constitution,
            wit: loadoutData.stats.wit,
            weapon_id: loadoutData.weapon_id,
        });
    } catch (e) {
        loadoutErrors.value = { general: e.response?.data?.error || 'Failed to load loadout data.' };
    }
};

const handleSaveLoadout = async () => {
    loadoutErrors.value = {};
    loadoutMessage.value = '';
    savingLoadout.value = true;
    try {
        const response = await updateCharacterLoadout(loadoutForm.value);
        if (response.character) gameState.value.character = response.character;
        Object.assign(loadoutForm.value, {
            strength: response.stats.strength,
            dexterity: response.stats.dexterity,
            constitution: response.stats.constitution,
            wit: response.stats.wit,
            weapon_id: response.weapon_id,
        });
        loadoutMessage.value = 'Loadout updated.';
        await fetchGameData();
    } catch (e) {
        loadoutErrors.value = e.response?.status === 422 ? e.response.data.errors : { general: e.response?.data?.error || 'Failed to update loadout.' };
    } finally {
        savingLoadout.value = false;
    }
};

onMounted(async () => {
    await fetchGameData();
    await fetchLoadoutData();
    initCharacterWebSocket();
    if (gameState.value?.location?.id) {
        initLocationWebSocket(gameState.value.location.id);
    }
});

onUnmounted(() => {
    if (battleChannel) {
        window.Echo.leave(`battle.${battleChannel}`);
    }
    window.Echo.leave(`character.${myCharacterId.value}`);
    if (locationChannel) {
        window.Echo.leave(`location.${locationChannel}`);
    }
    stopLocalTimer();
});

</script>

<template>
    <Head :title="fightState ? `Fight #${fightState.id}` : 'Game Lobby'" />

    <AuthenticatedLayout>
        <template #header>
            <div v-if="fightState" class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Battlegrounds: Fight #{{ fightState.id }}
                </h2>
                <div class="flex gap-4 items-center">
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
            <h2 v-else class="text-xl font-semibold leading-tight text-gray-800">
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
                    <!-- Character Panel (Shared or specific positions) -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg md:col-span-1">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Your Character</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-500 text-sm">Name</span>
                                        <CurrencyDisplay :copper="gameState.character.currency_copper || 0" />
                                    </div>
                                    <div class="font-semibold text-xl">{{ gameState.character.name }}</div>
                                </div>
                                <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                                    <span class="font-medium">Health</span>
                                    <span class="text-green-600 font-bold">{{ gameState.character.hp }} / {{ gameState.character.max_hp }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50 p-3 rounded">
                                    <div>
                                        <div class="text-gray-500 text-xs">Strength</div>
                                        <div class="font-bold">{{ gameState.character.stats.strength }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Dexterity</div>
                                        <div class="font-bold">{{ gameState.character.stats.dexterity }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Constitution</div>
                                        <div class="font-bold">{{ gameState.character.stats.constitution }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Wit</div>
                                        <div class="font-bold">{{ gameState.character.stats.wit }}</div>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-gray-500 text-sm">Weapon</span>
                                    <div class="capitalize text-gray-700 font-medium">{{ typeof gameState.character.weapon === 'string' ? gameState.character.weapon : gameState.character.weapon?.name }}</div>
                                </div>
                            </div>

                            <!-- Loadout editing only in Lobby -->
                            <div v-if="!fightState" class="mt-6 border-t pt-4">
                                <h4 class="text-sm font-semibold uppercase text-gray-500 mb-2">Edit Loadout</h4>
                                <div v-if="!loadout.can_edit" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3 mb-3">
                                    {{ loadout.blocked_reason || 'Loadout editing is unavailable right now.' }}
                                </div>
                                <div v-if="loadoutErrors.general" class="text-sm text-red-600 mb-2">
                                    {{ loadoutErrors.general }}
                                </div>
                                <div v-if="loadoutMessage" class="text-sm text-green-600 mb-2">
                                    {{ loadoutMessage }}
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Strength</label>
                                        <input v-model.number="loadoutForm.strength" type="number" class="w-full rounded border-gray-300 text-sm" :disabled="!loadout.can_edit || savingLoadout" />
                                        <div v-if="loadoutErrors.strength" class="text-xs text-red-600 mt-1">{{ loadoutErrors.strength[0] }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Dexterity</label>
                                        <input v-model.number="loadoutForm.dexterity" type="number" class="w-full rounded border-gray-300 text-sm" :disabled="!loadout.can_edit || savingLoadout" />
                                        <div v-if="loadoutErrors.dexterity" class="text-xs text-red-600 mt-1">{{ loadoutErrors.dexterity[0] }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Constitution</label>
                                        <input v-model.number="loadoutForm.constitution" type="number" class="w-full rounded border-gray-300 text-sm" :disabled="!loadout.can_edit || savingLoadout" />
                                        <div v-if="loadoutErrors.constitution" class="text-xs text-red-600 mt-1">{{ loadoutErrors.constitution[0] }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Wit</label>
                                        <input v-model.number="loadoutForm.wit" type="number" class="w-full rounded border-gray-300 text-sm" :disabled="!loadout.can_edit || savingLoadout" />
                                        <div v-if="loadoutErrors.wit" class="text-xs text-red-600 mt-1">{{ loadoutErrors.wit[0] }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Weapon</label>
                                        <select v-model="loadoutForm.weapon_id" class="w-full rounded border-gray-300 text-sm" :disabled="!loadout.can_edit || savingLoadout">
                                            <option :value="null">Unarmed</option>
                                            <option v-for="weapon in weapons" :key="weapon.id" :value="weapon.id">{{ weapon.name }}</option>
                                        </select>
                                    </div>
                                    <button @click="handleSaveLoadout" class="w-full bg-gray-900 hover:bg-gray-800 text-white py-2 rounded text-sm font-semibold disabled:opacity-60" :disabled="!loadout.can_edit || savingLoadout">
                                        {{ savingLoadout ? 'Saving...' : 'Save Loadout' }}
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Battle Stats/Info if in fight -->
                            <div v-else class="mt-6 border-t pt-4 text-sm">
                                <h4 class="text-xs font-semibold uppercase text-gray-400 mb-4 tracking-wider">Battle Status</h4>
                                <div v-for="p in fightState.participants" :key="p.character_id" class="mb-4 p-3 border rounded shadow-sm bg-gray-50" :class="{'ring-2 ring-red-400 bg-white': fightState.actions_submitted.includes(p.character_id)}">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-bold text-gray-800">{{ p.name }}</span>
                                        <span v-if="fightState.actions_submitted.includes(p.character_id)" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-black tracking-tighter">READY</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden shadow-inner">
                                        <div class="bg-red-500 h-full transition-all duration-700 ease-out" :style="`width: ${(p.hp / p.max_hp) * 100}%`"></div>
                                    </div>
                                    <div class="flex justify-between items-center mt-1.5">
                                        <span class="text-[10px] text-gray-500 italic">{{ p.weapon || 'Unarmed' }}</span>
                                        <span class="text-[10px] font-black text-gray-700">{{ p.hp }} / {{ p.max_hp }} HP</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Panel (Location or Battle Map) -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg md:col-span-2">
                        <div class="p-6 text-gray-900">
                            <!-- Battle UI -->
                            <div v-if="fightState" class="space-y-6">
                                <div v-if="fightState.status === 'waiting'" class="bg-yellow-50 border border-yellow-200 p-6 rounded-lg text-center">
                                    <h3 class="font-bold text-lg">Waiting for opponent</h3>
                                    <p class="text-gray-600">The battle will automatically begin when a second player joins.</p>
                                    <button @click="handleCancelFight" class="mt-4 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded">Cancel Fight</button>
                                </div>
                                <div v-else-if="fightState.status === 'finished'" class="bg-gray-100 border p-6 rounded-lg text-center">
                                    <h3 class="font-bold text-lg mb-2">Battle Concluded</h3>
                                    <button @click="fightState = null" class="text-blue-600 hover:underline">Return to Lobby</button>
                                </div>
                                <template v-else>
                                    <div class="bg-white rounded-lg p-4 shadow-sm border" v-if="fightState.map && fightState.positions">
                                        <h3 class="font-bold text-lg mb-3">Tactical Map</h3>
                                        <FightMap :map="fightState.map" :positions="fightState.positions" :myCharacterId="myCharacterId" :selectedTile="selectedTile" @tileSelected="handleTileSelected" />
                                    </div>

                                    <div v-if="amICommitted" class="bg-green-50 border border-green-200 p-6 rounded-lg text-center">
                                        <h3 class="font-bold text-green-800 mb-2">Actions Submitted!</h3>
                                        <p class="text-green-700 text-sm">Waiting for opponent or round timer...</p>
                                    </div>
                                    <div v-else class="space-y-4">
                                        <div v-if="actionMode !== 'none'" class="bg-gray-50 p-4 border rounded">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="font-bold capitalize">{{ actionMode }} Action</span>
                                                <button @click="clearSelection" class="text-xs text-blue-600">Clear</button>
                                            </div>
                                            <div v-if="actionMode === 'move'" class="space-y-3">
                                                <div class="text-sm">Target: ({{ selectedTile.x }}, {{ selectedTile.y }})</div>
                                                <BlockSelector v-model="selectedBlocks" />
                                            </div>
                                            <div v-else class="text-sm text-gray-600">Click actions below to queue your turn.</div>
                                        </div>
                                        <FightActionPanel v-if="actionMode !== 'none'" ref="actionPanelRef" :apAvailable="apAvailableForQueue" :disabled="submitting" @submitActions="handleQueueSubmit" />
                                        <div v-else class="text-sm text-gray-500 bg-gray-50 border border-dashed rounded p-4 text-center">
                                            Select an empty adjacent tile to move, or click an adjacent enemy to attack.
                                        </div>
                                    </div>
                                </template>
                                <FightLog ref="logRef" :fightId="fightState.id" />
                            </div>

                            <!-- Lobby UI -->
                            <div v-else>
                                <h3 class="text-lg font-bold mb-2">Location: {{ gameState.location.name }}</h3>
                                <p class="text-gray-600 mb-6 italic">{{ gameState.location.description }}</p>

                                <div v-if="gameState.currentFight && gameState.currentFight.state === 'waiting'" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold text-yellow-800">You are waiting in Fight #{{ gameState.currentFight.id }}</div>
                                        <div class="text-sm text-yellow-700">{{ gameState.currentFight.participant_ids.length }} / 2 joined</div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="fetchFightState(gameState.currentFight.id)" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded text-sm font-bold">Open Fight</button>
                                        <button @click="handleCancelFight" class="bg-gray-800 text-white py-2 px-4 rounded text-sm font-bold">Cancel</button>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center border-b pb-2 mb-4">
                                    <h4 class="font-semibold text-lg text-red-800">Available Fights</h4>
                                    <button @click="handleCreateFight" :disabled="!gameState.canCreateFight" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow disabled:opacity-50">Create New Fight</button>
                                </div>

                                <ul v-if="fights.length > 0" class="space-y-3">
                                    <li v-for="fight in fights" :key="fight.id" class="flex items-center justify-between p-4 border rounded hover:border-red-300 transition-colors">
                                        <div>
                                            <div class="font-medium">Fight #{{ fight.id }}</div>
                                            <div class="text-sm text-gray-500">Status: <span class="uppercase font-semibold text-yellow-600">{{ fight.state }}</span> - {{ fight.participants.length }} / 2</div>
                                        </div>
                                        <button @click="handleJoinFight(fight.id)" :disabled="!gameState.canCreateFight || fight.state !== 'waiting'" class="bg-gray-800 hover:bg-gray-900 text-white py-1.5 px-4 rounded text-sm disabled:opacity-50">Join Fight</button>
                                    </li>
                                </ul>
                                <div v-else class="text-center py-8 text-gray-500 bg-gray-50 rounded border border-dashed">No active fights. Create one!</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
