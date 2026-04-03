<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, onUnmounted, watch } from 'vue';
import FightLog from '@/Components/Fight/FightLog.vue';
import FightMap from '@/Components/Fight/FightMap.vue';
import BlockSelector from '@/Components/Fight/BlockSelector.vue';
import FightActionPanel from '@/Components/Fight/FightActionPanel.vue';
import CurrencyDisplay from '@/Components/CurrencyDisplay.vue';
import ChatPanel from '@/Components/Chat/ChatPanel.vue';
import {
    getGameState,
    getAvailableFights,
    createFight,
    joinFight,
    cancelFight,
    getCharacterLoadout,
    updateCharacterLoadout,
    equipBackpackItem,
    unequipBackpackItem,
    getLocations,
    changeLocation,
    getFightState,
    submitActions,
    openStore,
    buyStoreItem,
} from '@/api/gameApi';

const page = usePage();
const gameState = ref(null);
const fights = ref([]);
const locations = ref([]);
const loadout = ref({
    stats: { strength: 4, dexterity: 4, constitution: 4, wit: 4 },
    equipment: { main_hand: null, seal_1: null, seal_2: null, seal_3: null, seal_4: null, helmet: null, chest: null, legs: null, gloves: null },
    backpack: [],
    can_edit: true,
    blocked_reason: null,
});
const loadoutForm = ref({ strength: 4, dexterity: 4, constitution: 4, wit: 4 });
const loadoutErrors = ref({});
const loadoutMessage = ref('');
const savingLoadout = ref(false);
const changingLocation = ref(false);
const locationError = ref('');

const storeState = ref(null);
const loadingStore = ref(false);
const buyingItem = ref(false);

const equipmentError = ref('');
const equipmentMessage = ref('');
const savingEquipment = ref(false);

const waitingTimerRemaining = ref(0);
const createMaxPlayers = ref(null);
const createWaitMinutes = ref(null);
const lastLocationId = ref(null);

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
let refreshInterval = null;

const myCharacterId = computed(() => gameState.value?.character?.id);
const myTeam = computed(() => {
    if (!fightState.value?.participants) return null;
    const me = fightState.value.participants.find(p => p.character_id === myCharacterId.value);
    return me?.team || null;
});

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
        locations.value = await getLocations();
        if (gameState.value?.location && lastLocationId.value !== gameState.value.location.id) {
            const defaultsMax = gameState.value.location.max_players;
            const defaultsTimeout = gameState.value.location.start_timeout_seconds;
            createMaxPlayers.value = defaultsMax !== undefined ? defaultsMax : null;
            createWaitMinutes.value = defaultsTimeout ? Math.ceil(defaultsTimeout / 60) : null;
            lastLocationId.value = gameState.value.location.id;
        }

        if (gameState.value?.currentFight?.state === 'waiting') {
            if (gameState.value.currentFight.timer_remaining !== undefined) {
                waitingTimerRemaining.value = gameState.value.currentFight.timer_remaining;
            } else if (gameState.value.currentFight.start_timeout_seconds) {
                waitingTimerRemaining.value = gameState.value.currentFight.start_timeout_seconds;
            }
        } else {
            waitingTimerRemaining.value = 0;
        }
        
        // If there's an active or waiting fight, fetch its state
        if (gameState.value.currentFight) {
            await fetchFightState(gameState.value.currentFight.id);
        } else {
            fightState.value = null;
        }

        if (gameState.value?.location?.name === 'Shop') {
            handleOpenStore();
        } else {
            storeState.value = null;
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
        if (data && !data.id && data.battle_id) data.id = data.battle_id;
        fightState.value = data;
        startLocalTimer();
        initWebSocket(id);
    } catch (e) {
        console.error("Failed to fetch fight state", e);
    }
};

let battleChannel = null;

const clearBattleChannel = () => {
    if (battleChannel) {
        window.Echo.leave(`battle.${battleChannel}`);
        battleChannel = null;
    }
};

const initWebSocket = (battleId) => {
    // Cleanup existing channel if any
    if (battleChannel) {
        window.Echo.leave(`battle.${battleChannel}`);
    }
    battleChannel = battleId;

    const onUpdate = (payload) => {
        console.log('[Echo] Battle Update:', payload);
        const data = payload?.payload ?? payload;
        if (fightState.value) {
            fightState.value.round = data.round;
            data.players.forEach(p => {
                const existing = fightState.value.participants.find(part => part.character_id === p.character_id);
                if (existing) existing.hp = p.hp;
                if (existing && p.team) existing.team = p.team;
                if (p.character_id === myCharacterId.value && gameState.value?.character) {
                    gameState.value.character.hp = p.hp;
                }
            });
            fightState.value.positions = data.players.map(p => ({ character_id: p.character_id, x: p.x, y: p.y, team: p.team }));
        }
        if (logRef.value && data.events) {
            logRef.value.pushRound(data.round, data.events);
        }
    };

    const onRoundStarted = (payload) => {
        console.log('[Echo] Round Started:', payload);
        const data = payload?.payload ?? payload;
        if (fightState.value) {
            Object.assign(fightState.value, {
                round: data.round,
                timer_remaining: data.timeout,
                status: 'active',
                actions_submitted: []
            });
        }
        clearSelection();
        if (actionPanelRef.value) actionPanelRef.value.clearQueue();
        if (logRef.value) logRef.value.refresh();
    };

    const onBattleEnded = async (payload) => {
        console.log('[Echo] Battle Ended:', payload);
        const data = payload?.payload ?? payload;
        if (fightState.value) fightState.value.status = 'finished';
        stopLocalTimer();
        if (logRef.value && fightState.value?.id) {
            await logRef.value.refresh();
        }
        if (fightState.value?.id) {
            await fetchFightState(fightState.value.id);
        }
        clearBattleChannel();
        await fetchGameData();
    };

    const onBattleJoined = (payload) => {
        console.log('[Echo] Battle Joined:', payload);
        const data = payload?.payload ?? payload;
        // Ensure all required fields exist to prevent UI crashes
        if (!data.actions_submitted) data.actions_submitted = [];
        if (!data.id && data.battle_id) data.id = data.battle_id;
        
        fightState.value = data;
        if (fightState.value && !fightState.value.id && fightState.value.battle_id) {
            fightState.value.id = fightState.value.battle_id;
        }
        startLocalTimer();
        if (logRef.value) logRef.value.refresh();
    };

    const onCommitted = (payload) => {
        console.log('[Echo] Battle Committed:', payload);
        const data = payload?.payload ?? payload;
        if (fightState.value) {
            fightState.value.actions_submitted = data.committed_character_ids;
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
            const data = payload?.payload ?? payload;
            if (gameState.value?.character && gameState.value.character.id === data.character_id) {
                gameState.value.character.currency_copper = data.copper;
            }
        })
        .listen('.store.state', (payload) => {
            console.log('[Echo] Store State:', payload);
            const data = payload?.payload ?? payload;
            storeState.value = data.items;
            loadingStore.value = false;
        })
        .listen('.inventory.update', (payload) => {
            console.log('[Echo] Inventory Update:', payload);
            fetchLoadoutData();
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
            const data = payload?.payload ?? payload;
            const exists = fights.value.find(f => f.id === data.battle.id);
            if (!exists) {
                fights.value.push(data.battle);
            }
        })
        .listen('.battle.removed', (payload) => {
            console.log('[Echo] Battle Removed:', payload);
            const data = payload?.payload ?? payload;
            fights.value = fights.value.filter(f => f.id !== data.battle_id);
        });
};


const handleQueueSubmit = async (queuedActions) => {
    if (queuedActions.filter(a => a.type === 'attack').length > 2) return alert('Max 2 attacks.');
    
    const moveAction = actionMode.value === 'move' && selectedTile.value 
        ? { type: 'move', target: { x: selectedTile.value.x, y: selectedTile.value.y }, blocks: selectedBlocks.value }
        : null;

    if (queuedActions.length + (moveAction ? moveCost.value : 0) > apAvailable.value) return alert('Not enough AP.');
    
    const attackTargetId = actionMode.value === 'attack' && selectedEnemy.value
        ? getCharacterAt(selectedEnemy.value)
        : null;
    const normalizedActions = queuedActions.map(action => {
        if (action.type === 'attack' && attackTargetId) {
            return { ...action, target_id: attackTargetId };
        }
        return action;
    });
    const payload = moveAction ? [...normalizedActions, moveAction] : [...normalizedActions];
    if (!payload.length) return alert('No actions.');

    submitting.value = true;
    try {
        if (!fightState.value?.id) {
            throw new Error('Fight is not ready yet. Please try again in a moment.');
        }
        await submitActions(fightState.value.id, payload);
        await fetchFightState(fightState.value.id);
        if (actionPanelRef.value) actionPanelRef.value.clearQueue();
        clearSelection();
    } catch (e) {
        alert(e.response?.data?.error || e.message || 'Failed to submit.');
    } finally {
        submitting.value = false;
    }
};

const handleCreateFight = async () => {
    try {
        const maxParticipants = createMaxPlayers.value !== null && createMaxPlayers.value !== ''
            ? Number(createMaxPlayers.value)
            : null;
        const waitMinutes = createWaitMinutes.value !== null && createWaitMinutes.value !== ''
            ? Number(createWaitMinutes.value)
            : null;
        if (waitMinutes !== null && waitMinutes > 10) {
            alert('Max wait time is 10 minutes.');
            return;
        }
        const waitSeconds = waitMinutes !== null ? Math.round(waitMinutes * 60) : null;

        const payload = {
            max_participants: maxParticipants,
            start_timeout_seconds: waitSeconds,
        };
        const response = await createFight(payload);
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
        clearBattleChannel();
        await fetchGameData();
        await fetchLoadoutData();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to cancel fight');
    }
};

const handleReturnToLobby = async () => {
    fightState.value = null;
    stopLocalTimer();
    clearBattleChannel();
    await fetchGameData();
};

const handleChangeLocation = async (locationId) => {
    if (changingLocation.value) return;
    if (!gameState.value?.canLeaveLocation) {
        locationError.value = 'You cannot change locations while in a fight.';
        return;
    }
    if (gameState.value?.location?.id === locationId) return;

    changingLocation.value = true;
    locationError.value = '';
    try {
        await changeLocation(locationId);
        await fetchGameData();
        if (gameState.value?.location?.id) {
            initLocationWebSocket(gameState.value.location.id);
        }
        if (gameState.value?.location?.name === 'Shop') {
            handleOpenStore();
        } else {
            storeState.value = null;
        }
    } catch (e) {
        locationError.value = e.response?.data?.error || 'Failed to change location.';
    } finally {
        changingLocation.value = false;
    }
};

const handleOpenStore = async () => {
    if (gameState.value?.location?.name === 'Shop') {
        loadingStore.value = true;
        try {
            const response = await openStore(1);
            if (response?.items) {
                storeState.value = response.items;
            }
        } catch (e) {
            console.error("Failed to open store", e);
        } finally {
            loadingStore.value = false;
        }
    } else {
        storeState.value = null;
    }
};

const handleBuyItem = async (storeItemId) => {
    buyingItem.value = true;
    try {
        await buyStoreItem(storeItemId);
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to buy store item');
    } finally {
        buyingItem.value = false;
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
            if (fightState.value.timer_remaining === 0 && fightState.value?.id) {
                fetchFightState(fightState.value.id);
            }
        }

        if (waitingTimerRemaining.value > 0) {
            waitingTimerRemaining.value--;
        }
    }, 1000);

    refreshInterval = setInterval(() => {
        if (fightState.value?.status === 'active' && fightState.value?.id && !submitting.value) {
            fetchFightState(fightState.value.id);
        }
    }, 5000);
};

const stopLocalTimer = () => {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = null;
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = null;
};

const formatTime = (s) => `${Math.floor(s / 60)}:${(s % 60).toString().padStart(2, '0')}`;

const fetchLoadoutData = async () => {
    try {
        const loadoutData = await getCharacterLoadout();
        loadout.value = {
            ...loadout.value,
            ...loadoutData,
            equipment: loadoutData.equipment ?? { main_hand: null, seal_1: null, seal_2: null, seal_3: null, seal_4: null, helmet: null, chest: null, legs: null, gloves: null },
            backpack: loadoutData.backpack ?? [],
        };
        Object.assign(loadoutForm.value, {
            strength: loadoutData.stats.strength,
            dexterity: loadoutData.stats.dexterity,
            constitution: loadoutData.stats.constitution,
            wit: loadoutData.stats.wit,
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
        });
        loadoutMessage.value = 'Loadout updated.';
        await fetchGameData();
    } catch (e) {
        loadoutErrors.value = e.response?.status === 422 ? e.response.data.errors : { general: e.response?.data?.error || 'Failed to update loadout.' };
    } finally {
        savingLoadout.value = false;
    }
};

const canEquipItem = (item) => {
    if (!item || !gameState.value?.character?.stats) return false;
    return gameState.value.character.stats.strength >= (item.required_strength ?? 0) &&
        gameState.value.character.stats.wit >= (item.required_wit ?? 0) &&
        gameState.value.character.stats.dexterity >= (item.required_dexterity ?? 0) &&
        gameState.value.character.stats.constitution >= (item.required_constitution ?? 0);
};

const sealSlots = ['seal_1', 'seal_2', 'seal_3', 'seal_4'];
const armorSlotLabels = {
    helmet: 'Helmet',
    chest: 'Chest',
    legs: 'Legs',
    gloves: 'Gloves',
};

const resolveEquipSlot = (item) => {
    if (item?.type === 'seal') {
        const openSlot = sealSlots.find(slot => !loadout.value?.equipment?.[slot]);
        return openSlot || sealSlots[0];
    }
    if (item?.type === 'armor') {
        const subtype = item.armor_subtype;
        const slotBySubtype = {
            helmet: 'helmet',
            body: 'chest',
            boots: 'legs',
            gloves: 'gloves',
        };
        return slotBySubtype[subtype] || 'chest';
    }
    return 'main_hand';
};

const handleEquipItem = async (item, slot = null) => {
    if (!item) return;
    equipmentError.value = '';
    equipmentMessage.value = '';
    savingEquipment.value = true;
    try {
        const targetSlot = slot || resolveEquipSlot(item);
        const response = await equipBackpackItem({ item_id: item.id, slot: targetSlot });
        if (response.character) gameState.value.character = response.character;
        if (response.equipment) loadout.value.equipment = response.equipment;
        if (response.backpack) loadout.value.backpack = response.backpack;
        equipmentMessage.value = 'Equipped.';
    } catch (e) {
        equipmentError.value = e.response?.data?.error || 'Failed to equip item.';
    } finally {
        savingEquipment.value = false;
    }
};

const handleUnequipItem = async (slot = 'main_hand') => {
    equipmentError.value = '';
    equipmentMessage.value = '';
    savingEquipment.value = true;
    try {
        const response = await unequipBackpackItem({ slot });
        if (response.character) gameState.value.character = response.character;
        if (response.equipment) loadout.value.equipment = response.equipment;
        if (response.backpack) loadout.value.backpack = response.backpack;
        equipmentMessage.value = 'Unequipped.';
    } catch (e) {
        equipmentError.value = e.response?.data?.error || 'Failed to unequip item.';
    } finally {
        savingEquipment.value = false;
    }
};

onMounted(async () => {
    await fetchGameData();
    await fetchLoadoutData();
    watch(() => gameState.value?.character?.id, (newId) => {
        if (newId) {
            initCharacterWebSocket();
        }
    }, { immediate: true });
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

                <div v-else class="grid grid-cols-1 md:grid-cols-4 gap-6">
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
                                    <div class="capitalize text-gray-700 font-medium">{{ gameState.character.weapon ? (typeof gameState.character.weapon === 'string' ? gameState.character.weapon : gameState.character.weapon?.name) : 'Unarmed' }}</div>
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
                                    <button @click="handleSaveLoadout" class="w-full bg-gray-900 hover:bg-gray-800 text-white py-2 rounded text-sm font-semibold disabled:opacity-60" :disabled="!loadout.can_edit || savingLoadout">
                                        {{ savingLoadout ? 'Saving...' : 'Save Loadout' }}
                                    </button>
                                </div>

                                <div class="mt-5 border-t pt-4">
                                    <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">Equipment</h4>
                                    <div class="bg-gray-50 p-3 rounded flex items-center justify-between">
                                        <div>
                                            <div class="text-[10px] uppercase text-gray-400">Main Hand</div>
                                            <div class="font-medium text-gray-800">
                                                {{ loadout.equipment.main_hand ? loadout.equipment.main_hand.name : 'Empty' }}
                                            </div>
                                            <div v-if="loadout.equipment.main_hand" class="text-[10px] text-gray-500 mt-1">
                                                DMG {{ loadout.equipment.main_hand.min_damage }}-{{ loadout.equipment.main_hand.max_damage }}
                                                <span v-if="loadout.equipment.main_hand.damage_type">� {{ loadout.equipment.main_hand.damage_type }}</span>
                                            </div>
                                        </div>
                                        <button v-if="loadout.equipment.main_hand" @click="handleUnequipItem('main_hand')" class="text-xs bg-gray-800 text-white px-3 py-1.5 rounded disabled:opacity-60" :disabled="!loadout.can_edit || savingEquipment">
                                            {{ savingEquipment ? 'Working...' : 'Unequip' }}
                                        </button>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div v-for="slot in sealSlots" :key="slot" class="bg-gray-50 p-3 rounded flex items-center justify-between">
                                            <div>
                                                <div class="text-[10px] uppercase text-gray-400">{{ slot.replace('seal_', 'Seal ') }}</div>
                                                <div class="font-medium text-gray-800">
                                                    {{ loadout.equipment[slot] ? loadout.equipment[slot].name : 'Empty' }}
                                                </div>
                                            </div>
                                            <button v-if="loadout.equipment[slot]" @click="handleUnequipItem(slot)" class="text-xs bg-gray-800 text-white px-3 py-1.5 rounded disabled:opacity-60" :disabled="!loadout.can_edit || savingEquipment">
                                                {{ savingEquipment ? 'Working...' : 'Unequip' }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div v-for="(label, slot) in armorSlotLabels" :key="slot" class="bg-gray-50 p-3 rounded flex items-center justify-between">
                                            <div>
                                                <div class="text-[10px] uppercase text-gray-400">{{ label }}</div>
                                                <div class="font-medium text-gray-800">
                                                    {{ loadout.equipment[slot] ? loadout.equipment[slot].name : 'Empty' }}
                                                </div>
                                            </div>
                                            <button v-if="loadout.equipment[slot]" @click="handleUnequipItem(slot)" class="text-xs bg-gray-800 text-white px-3 py-1.5 rounded disabled:opacity-60" :disabled="!loadout.can_edit || savingEquipment">
                                                {{ savingEquipment ? 'Working...' : 'Unequip' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">Backpack</h4>
                                    <div v-if="equipmentError" class="text-sm text-red-600 mb-2">
                                        {{ equipmentError }}
                                    </div>
                                    <div v-if="equipmentMessage" class="text-sm text-green-600 mb-2">
                                        {{ equipmentMessage }}
                                    </div>
                                    <div v-if="loadout.backpack.length === 0" class="text-xs text-gray-500 bg-gray-50 border border-dashed rounded p-3">
                                        Backpack is empty.
                                    </div>
                                    <ul v-else class="space-y-2">
                                        <li v-for="item in loadout.backpack" :key="item.id" class="flex items-center justify-between p-3 border rounded bg-white">
                                            <div>
                                                <div class="font-medium text-sm">
                                                    {{ item.name }}
                                                    <span v-if="item.quantity > 1" class="text-xs text-gray-500">x{{ item.quantity }}</span>
                                                </div>
                                                <div class="text-[10px] text-gray-500">
                                                    DMG {{ item.min_damage }}-{{ item.max_damage }}
                                                    <span v-if="item.damage_type">� {{ item.damage_type }}</span>
                                                </div>
                                                <div v-if="item.ad_armor" class="text-[10px] text-gray-500">
                                                    Armor {{ item.ad_armor }}
                                                </div>
                                                <div class="text-[10px] text-gray-400">
                                                    <span v-if="item.required_dexterity || item.required_constitution">
                                                        Req DEX {{ item.required_dexterity }} / CON {{ item.required_constitution }}
                                                    </span>
                                                    <span v-else>
                                                        Req STR {{ item.required_strength }} / WIT {{ item.required_wit }}
                                                    </span>
                                                </div>
                                            </div>
                                            <button @click="handleEquipItem(item)" class="text-xs bg-gray-900 text-white px-3 py-1.5 rounded disabled:opacity-60" :disabled="!loadout.can_edit || savingEquipment || !canEquipItem(item)">
                                                {{ savingEquipment ? 'Working...' : 'Equip' }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Battle Stats/Info if in fight -->
                            <div v-else class="mt-6 border-t pt-4 text-sm">
                                <h4 class="text-xs font-semibold uppercase text-gray-400 mb-4 tracking-wider">Battle Status</h4>
                                <div v-for="p in fightState.participants" :key="p.character_id" class="mb-4 p-3 border rounded shadow-sm bg-gray-50" :class="{'ring-2 ring-red-400 bg-white': fightState.actions_submitted.includes(p.character_id)}">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-bold text-gray-800">
                                            {{ p.name }}
                                            <span v-if="p.team" class="ml-2 text-[10px] uppercase font-semibold"
                                                  :class="p.team === 'blue' ? 'text-blue-600' : 'text-red-600'">
                                                {{ p.team }}
                                            </span>
                                        </span>
                                        <span v-if="fightState.actions_submitted.includes(p.character_id)" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-black tracking-tighter">READY</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden shadow-inner">
                                        <div class="bg-red-500 h-full transition-all duration-700 ease-out" :style="`width: ${(p.hp / p.max_hp) * 100}%`"></div>
                                    </div>
                                    <div class="flex justify-between items-center mt-1.5">
                                        <span class="text-[10px] text-gray-500 italic">{{ p.weapon || 'Unarmed' }}</span>
                                        <span class="text-[10px] font-black text-gray-700">{{ p.hp }} / {{ p.max_hp }} HP</span>
                                    </div>
                                    <div v-if="p.additional_armor && (p.additional_armor.head || p.additional_armor.chest || p.additional_armor.legs || p.additional_armor.left_arm || p.additional_armor.right_arm)" class="text-[10px] text-gray-500 mt-1">
                                        Additional Armor:
                                        H {{ p.additional_armor.head || 0 }}
                                        C {{ p.additional_armor.chest || 0 }}
                                        L {{ p.additional_armor.legs || 0 }}
                                        LA {{ p.additional_armor.left_arm || 0 }}
                                        RA {{ p.additional_armor.right_arm || 0 }}
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
                                    <p class="text-gray-600">Fight #{{ fightState.id }} will automatically begin when a second player joins.</p>
                                    <button @click="handleCancelFight" class="mt-4 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded">Cancel Fight</button>
                                </div>
                                <div v-else-if="fightState.status === 'finished'" class="bg-gray-100 border p-6 rounded-lg text-center">
                                    <h3 class="font-bold text-lg mb-2">Battle Concluded</h3>
                                    <button @click="handleReturnToLobby" class="text-blue-600 hover:underline">Return to Lobby</button>
                                </div>
                                <template v-else>
                                    <div class="bg-white rounded-lg p-4 shadow-sm border" v-if="fightState.map && fightState.positions">
                                        <h3 class="font-bold text-lg mb-3">Tactical Map</h3>
                                        <FightMap :map="fightState.map" :positions="fightState.positions" :myCharacterId="myCharacterId" :myTeam="myTeam" :selectedTile="selectedTile" @tileSelected="handleTileSelected" />
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
                                <FightLog ref="logRef" :fightId="fightState.id" :fightStatus="fightState.status" :fightNumber="fightState.id" :participants="fightState.participants || []" />
                            </div>

                            <!-- Shop UI -->
                            <div v-else-if="gameState.location.name === 'Shop'">
                                <div class="flex items-center justify-between mb-6 pb-4 border-b">
                                    <div>
                                        <h3 class="text-xl font-black text-gray-900 tracking-tight">The Merchant</h3>
                                        <p class="text-sm text-gray-500 italic mt-1">{{ gameState.location.description }}</p>
                                    </div>
                                    <button
                                        v-for="loc in locations.filter(l => l.name !== 'Shop')"
                                        :key="loc.id"
                                        @click="handleChangeLocation(loc.id)"
                                        :disabled="changingLocation"
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded text-sm transition-colors border"
                                    >
                                        Leave Shop
                                    </button>
                                </div>

                                <div v-if="loadingStore" class="text-center py-8 text-gray-500 bg-gray-50 rounded border border-dashed">
                                    Loading store inventory...
                                </div>
                                <div v-else-if="!storeState || storeState.length === 0" class="text-center py-8 text-gray-500 bg-gray-50 rounded border border-dashed">
                                    The merchant has nothing to sell right now.
                                </div>
                                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-for="item in storeState" :key="item.store_item_id" class="flex flex-col justify-between p-4 border rounded shadow-sm bg-white hover:border-gray-300 transition-colors">
                                        <div class="mb-3 border-b border-gray-100 pb-3">
                                            <div class="flex justify-between items-start">
                                                <h4 class="font-bold text-gray-800 text-lg leading-tight">{{ item.item.name }}</h4>
                                                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700 uppercase tracking-wider">{{ item.item.type }}</span>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-2 font-medium">Price: <span class="text-yellow-600 font-bold ml-1">{{ item.price === 0 ? 'Free' : item.price + ' copper' }}</span></div>
                                        <div class="text-xs text-gray-600 space-y-1 mt-2">
                                            <div v-if="(item.item.min_damage || item.item.max_damage)">
                                                Damage: {{ item.item.min_damage }}-{{ item.item.max_damage }}<span v-if="item.item.damage_type"> ({{ item.item.damage_type }})</span>
                                            </div>
                                            <div v-if="item.item.flat_crit_bonus">Crit Chance: +{{ item.item.flat_crit_bonus }}%</div>
                                            <div v-if="item.item.max_damage_rating">Power: {{ item.item.max_damage_rating }}</div>
                                            <div v-if="item.item.archetype">Archetype: <span class="capitalize">{{ item.item.archetype }}</span></div>
                                            <div v-if="item.item.required_dexterity || item.item.required_constitution">
                                                Requirements:
                                                <span v-if="item.item.required_dexterity"> DEX {{ item.item.required_dexterity }}</span>
                                                <span v-if="item.item.required_constitution"> CON {{ item.item.required_constitution }}</span>
                                            </div>
                                            <div v-else-if="item.item.required_strength || item.item.required_wit">
                                                Requirements:
                                                <span v-if="item.item.required_strength"> STR {{ item.item.required_strength }}</span>
                                                <span v-if="item.item.required_wit"> WIT {{ item.item.required_wit }}</span>
                                            </div>
                                        </div>
                                        </div>
                                        <button @click="handleBuyItem(item.store_item_id)" :disabled="buyingItem" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-2.5 px-4 rounded text-sm transition-colors disabled:opacity-50">
                                            {{ buyingItem ? 'Processing...' : 'Buy' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Lobby UI -->
                            <div v-else>
                                <h3 class="text-lg font-bold mb-2">Location: {{ gameState.location.name }}</h3>
                                <p class="text-gray-600 mb-6 italic">{{ gameState.location.description }}</p>

                                <div class="mb-6 border-b pb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-lg text-gray-800">Travel</h4>
                                        <span class="text-xs uppercase tracking-wide text-gray-400">Locations</span>
                                    </div>
                                    <p class="text-sm text-gray-500 mb-3">Choose where to go next.</p>
                                    <div v-if="!gameState.canLeaveLocation" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3 mb-3">
                                        You cannot change locations while you are in a fight.
                                    </div>
                                    <div v-if="locationError" class="text-sm text-red-600 mb-2">
                                        {{ locationError }}
                                    </div>
                                    <ul v-if="locations.length" class="space-y-2">
                                        <li v-for="location in locations" :key="location.id" class="flex items-center justify-between p-3 border rounded bg-gray-50">
                                            <div>
                                                <div class="font-medium text-gray-800">{{ location.name }}</div>
                                                <div class="text-xs text-gray-500">{{ location.description }}</div>
                                            </div>
                                            <button
                                                @click="handleChangeLocation(location.id)"
                                                :disabled="changingLocation || !gameState.canLeaveLocation || location.id === gameState.location.id"
                                                class="text-xs font-semibold px-3 py-1.5 rounded border"
                                                :class="location.id === gameState.location.id ? 'bg-gray-200 text-gray-600 border-gray-200' : 'bg-gray-900 text-white border-gray-900 hover:bg-gray-800'"
                                            >
                                                {{ location.id === gameState.location.id ? 'Current' : (changingLocation ? 'Traveling...' : 'Travel') }}
                                            </button>
                                        </li>
                                    </ul>
                                    <div v-else class="text-sm text-gray-500 bg-gray-50 border border-dashed rounded p-3">
                                        No locations available.
                                    </div>
                                </div>

                                <div v-if="gameState.currentFight && gameState.currentFight.state === 'waiting'" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold text-yellow-800">You are waiting in Fight #{{ gameState.currentFight.id }}</div>
                                        <div class="text-sm text-yellow-700">
                                            {{ gameState.currentFight.participant_ids.length }} / {{ gameState.currentFight.max_participants || '∞' }} joined
                                        </div>
                                        <div v-if="waitingTimerRemaining > 0" class="text-xs text-yellow-700 mt-1">
                                            Starts in {{ formatTime(waitingTimerRemaining) }}
                                        </div>
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

                                <div class="mb-5 p-4 bg-gray-50 border rounded">
                                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-3">Create Settings</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <label class="text-sm text-gray-700">
                                            Max Players
                                            <input
                                                v-model="createMaxPlayers"
                                                type="number"
                                                min="2"
                                                class="mt-1 w-full border rounded px-2 py-1.5 text-sm"
                                                :placeholder="gameState.location?.max_players ?? '∞'"
                                            />
                                        </label>
                                        <label class="text-sm text-gray-700">
                                            Wait Time (minutes, max 10)
                                            <input
                                                v-model="createWaitMinutes"
                                                type="number"
                                                min="1"
                                                max="10"
                                                step="1"
                                                class="mt-1 w-full border rounded px-2 py-1.5 text-sm"
                                                :placeholder="gameState.location?.start_timeout_seconds ? Math.ceil(gameState.location.start_timeout_seconds / 60) : 10"
                                            />
                                        </label>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2">Leave empty to use location defaults.</div>
                                </div>

                                <ul v-if="fights.length > 0" class="space-y-3">
                                    <li v-for="fight in fights" :key="fight.id" class="flex items-center justify-between p-4 border rounded hover:border-red-300 transition-colors">
                                        <div>
                                            <div class="font-medium">Fight #{{ fight.id }}</div>
                                            <div class="text-sm text-gray-500">
                                                Status: <span class="uppercase font-semibold text-yellow-600">{{ fight.state }}</span> -
                                                {{ fight.participants.length }} / {{ fight.max_participants || '∞' }}
                                            </div>
                                            <div v-if="fight.state === 'waiting' && fight.start_timeout_seconds" class="text-xs text-gray-400 mt-1">
                                                Starts in {{ formatTime(fight.start_timeout_seconds) }}
                                            </div>
                                        </div>
                                        <button @click="handleJoinFight(fight.id)" :disabled="!gameState.canCreateFight || fight.state !== 'waiting'" class="bg-gray-800 hover:bg-gray-900 text-white py-1.5 px-4 rounded text-sm disabled:opacity-50">Join Fight</button>
                                    </li>
                                </ul>
                                <div v-else class="text-center py-8 text-gray-500 bg-gray-50 rounded border border-dashed">No active fights. Create one!</div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-1">
                        <ChatPanel
                            :character="gameState.character"
                            :location="gameState.location"
                            :currentFight="gameState.currentFight"
                        />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>









