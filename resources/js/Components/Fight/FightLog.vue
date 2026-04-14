<script setup>
import { ref, onMounted, watch } from 'vue';
import { getFightLogs } from '@/api/gameApi';

const props = defineProps({
    fightId: {
        type: Number,
        required: true
    },
    fightStatus: {
        type: String,
        required: false,
        default: null
    },
    fightNumber: {
        type: Number,
        required: false,
        default: null
    },
    participants: {
        type: Array,
        required: false,
        default: () => []
    }
});

const logs = ref([]);
const loading = ref(true);
const error = ref('');

const normalizeEvent = (event) => {
    if (event.type === 'attack') return event;
    if (event.type === 'hit') return { ...event, type: 'attack', outcome: 'hit' };
    if (event.type === 'crit') return { ...event, type: 'attack', outcome: 'hit', is_crit: true };
    if (event.type === 'max_damage') return { ...event, type: 'attack', outcome: 'hit', is_max: true };
    if (event.type === 'block') return { ...event, type: 'attack', outcome: 'block' };
    if (event.type === 'block_break') return { ...event, type: 'attack', outcome: 'block_break' };
    if (event.type === 'dodge') return { ...event, type: 'attack', outcome: 'dodge' };
    return event;
};

const dedupeEvents = (events) => {
    const seen = new Set();
    return events.filter((event) => {
        const key = [
            event.type,
            event.actor_id,
            event.target_id,
            event.zone,
            event.damage,
            event.outcome,
            event.is_crit ? 1 : 0,
            event.is_max ? 1 : 0,
            event.weapon_name,
            event.damage_type,
            event.occurred_at,
        ].join('|');
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
};

const normalizeRound = (round) => {
    const normalized = round.events.map(normalizeEvent);
    return { ...round, events: dedupeEvents(normalized) };
};

const fetchLogs = async () => {
    if (!props.fightId) return;
    loading.value = true;
    try {
        const data = await getFightLogs(props.fightId);
        logs.value = data.map(normalizeRound);
    } catch (e) {
        error.value = 'Failed to load combat logs.';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

watch(() => props.fightId, (newId) => {
    if (newId) fetchLogs();
});

onMounted(() => {
    fetchLogs();
});

defineExpose({
    refresh: fetchLogs,
    pushRound: (round, events) => {
        // Map camelCase from WebSocket to snake_case for visibility
        const mappedEvents = events.map(e => ({
            ...e,
            actor_id: e.actorId || e.actor_id,
            target_id: e.targetId || e.target_id,
        }));
        const normalizedEvents = dedupeEvents(mappedEvents.map(normalizeEvent));
        
        // Check if round already exists to avoid duplicates
        const existing = logs.value.find(r => r.round === round);
        if (existing) {
            existing.events = normalizedEvents;
        } else {
            logs.value.push({ round, events: normalizedEvents });
            // Sort by round desc or asc? The template loops as is. 
            // Usually logs are newest at top? No, Round 1, Round 2...
            logs.value.sort((a, b) => a.round - b.round);
        }
    }
});

const getZoneDisplay = (zone) => {
    if (!zone) return '';
    return zone.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const getActionIcon = (type) => {
    switch (type) {
        case 'attack': return '⚔️';
        case 'hit': return '💥';
        case 'crit': return '🔥';
        case 'max_damage': return '⚡';
        case 'dodge': return '💨';
        case 'block': return '🛡️';
        case 'block_break': return '💢';
        case 'move': return '👟';
        case 'death': return '💀';
        case 'skip': return '⏭️';
        case 'victory': return '🏆';
        default: return 'EVENT';
    }
};

const getNameById = (id) => {
    if (!id) return 'Unknown';
    const found = props.participants.find(p => p.character_id == id);
    return found?.name || `Player ${id}`;
};

const getAttackOutcome = (event) => {
    if (event.outcome) return event.outcome;
    if (event.type === 'block_break') return 'block_break';
    if (event.type === 'block') return 'block';
    if (event.type === 'dodge') return 'dodge';
    return 'hit';
};
</script>

<template>
    <div class="bg-gray-50 rounded-lg p-4 border h-96 overflow-y-auto">
        <h3 class="font-bold text-lg mb-4 sticky top-0 bg-gray-50 py-2 border-b">
            Combat Log
            <span v-if="fightNumber" class="text-sm font-normal text-gray-500 ml-2">Fight #{{ fightNumber }}</span>
        </h3>
        
        <div v-if="loading && fightStatus !== 'waiting'" class="text-sm text-gray-500">Loading logs...</div>
        <div v-else-if="fightStatus === 'waiting'" class="text-sm text-gray-500 italic text-center py-8">
            Battle not started yet. Waiting for an opponent.
        </div>
        <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
        <div v-else-if="logs.length === 0" class="text-sm text-gray-500 italic text-center py-8">
            No combat events yet. The battle has just begun!
        </div>
        
        <div v-else class="space-y-6">
            <div v-for="roundGroup in logs" :key="roundGroup.round" class="space-y-2">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Round {{ roundGroup.round }}</div>
                
                <ul class="space-y-1">
                    <li v-for="event in roundGroup.events" :key="event.occurred_at + '-' + event.actor_id + '-' + event.type" 
                        class="text-sm p-2 bg-white rounded shadow-sm border flex items-start gap-2"
                        :class="{
                            'border-orange-300 bg-orange-50': event.type === 'attack' && getAttackOutcome(event) === 'block_break',
                            'border-green-300 bg-green-50': event.type === 'attack' && getAttackOutcome(event) === 'block',
                            'border-blue-300 bg-blue-50': event.type === 'attack' && getAttackOutcome(event) === 'dodge',
                            'border-red-300 bg-red-50': event.type === 'attack' && getAttackOutcome(event) === 'hit',
                            'border-gray-200': event.type === 'move',
                            'border-purple-300 bg-purple-50': event.type === 'death',
                            'border-yellow-300 bg-yellow-50': event.type === 'skip',
                            'border-emerald-300 bg-emerald-50': event.type === 'victory'
                        }">
                        <span class="text-lg leading-none font-semibold" :title="event.type">{{ getActionIcon(event.type) }}</span>
                        <div class="flex-1">
                            <span v-if="event.type === 'attack'">
                                <span class="font-bold">{{ getNameById(event.actor_id) }}</span>
                                <span v-if="getAttackOutcome(event) === 'dodge'">
                                    <span class="text-blue-600 font-bold uppercase">missed</span> <span class="font-bold">{{ getNameById(event.target_id) }}</span>.
                                </span>
                                <span v-else-if="getAttackOutcome(event) === 'block'">
                                    hit <span class="font-bold">{{ getNameById(event.target_id) }}</span>
                                    <span v-if="event.weapon_name"> with <span class="italic text-gray-700">{{ event.weapon_name }}</span></span>
                                    <span v-if="event.damage_type" class="text-xs text-gray-500"> ({{ event.damage_type }})</span>
                                    in the {{ getZoneDisplay(event.zone) }} but the attack was <span class="text-green-700 font-bold">blocked</span>.
                                </span>
                                <span v-else-if="getAttackOutcome(event) === 'block_break'">
                                    <span class="text-orange-600 font-bold">BROKE BLOCK</span> of <span class="font-bold">{{ getNameById(event.target_id) }}</span> 
                                    <span v-if="event.weapon_name"> with <span class="italic text-gray-700">{{ event.weapon_name }}</span></span>
                                    <span v-if="event.damage_type" class="text-xs text-gray-500"> ({{ event.damage_type }})</span>
                                    in the {{ getZoneDisplay(event.zone) }} for <span class="text-red-600 font-bold">{{ event.damage }}</span> damage.
                                </span>
                                <span v-else>
                                    hit <span class="font-bold">{{ getNameById(event.target_id) }}</span> 
                                    <span v-if="event.weapon_name"> with <span class="italic text-gray-700">{{ event.weapon_name }}</span></span>
                                    <span v-if="event.damage_type" class="text-xs text-gray-500"> ({{ event.damage_type }})</span>
                                    in the {{ getZoneDisplay(event.zone) }} for <span class="text-red-600 font-bold">{{ event.damage }}</span> damage.
                                </span>
                                <span v-if="event.is_max && getAttackOutcome(event) !== 'block' && getAttackOutcome(event) !== 'dodge' && getAttackOutcome(event) !== 'parry'" class="ml-1 text-yellow-700 font-bold">MAX</span>
                                <span v-if="event.is_crit && getAttackOutcome(event) !== 'block' && getAttackOutcome(event) !== 'dodge' && getAttackOutcome(event) !== 'parry'" class="ml-1 text-pink-600 font-bold">CRIT</span>
                            </span>
                            <span v-else-if="event.type === 'move'">
                                <span class="font-bold">{{ getNameById(event.actor_id) }}</span> moved.
                            </span>
                            <span v-else-if="event.type === 'death'">
                                <span class="font-bold">{{ getNameById(event.actor_id) }}</span> has fallen in battle!
                            </span>
                            <span v-else-if="event.type === 'skip'">
                                <span class="font-bold">{{ getNameById(event.actor_id) }}</span> skipped the turn and auto-blocked.
                            </span>
                            <span v-else-if="event.type === 'victory'">
                                <span class="font-bold">{{ getNameById(event.actor_id) }}</span> wins the battle!
                            </span>
                            <span v-else class="text-gray-500">Unknown event type: {{ event.type }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
