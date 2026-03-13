<script setup>
import { ref, onMounted } from 'vue';
import { getFightLogs } from '@/api/gameApi';

const props = defineProps({
    fightId: {
        type: Number,
        required: true
    }
});

const logs = ref([]);
const loading = ref(true);
const error = ref('');

const fetchLogs = async () => {
    try {
        const data = await getFightLogs(props.fightId);
        logs.value = data;
    } catch (e) {
        error.value = 'Failed to load combat logs.';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchLogs();
});

defineExpose({
    refresh: fetchLogs
});

const getZoneDisplay = (zone) => {
    if (!zone) return '';
    return zone.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const getActionIcon = (type) => {
    switch (type) {
        case 'hit': return '💥';
        case 'max_damage': return '⚡';
        case 'miss': return '❌';
        case 'block': return '🛡️';
        case 'block_break': return '💢';
        case 'move': return '👟';
        case 'death': return '💀';
        default: return 'EVENT';
    }
};
</script>

<template>
    <div class="bg-gray-50 rounded-lg p-4 border h-96 overflow-y-auto">
        <h3 class="font-bold text-lg mb-4 sticky top-0 bg-gray-50 py-2 border-b">Combat Log</h3>
        
        <div v-if="loading" class="text-sm text-gray-500">Loading logs...</div>
        <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
        <div v-else-if="logs.length === 0" class="text-sm text-gray-500 italic text-center py-8">
            No combat events yet. The battle has just begun!
        </div>
        
        <div v-else class="space-y-6">
            <div v-for="roundGroup in logs" :key="roundGroup.round" class="space-y-2">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Round {{ roundGroup.round }}</div>
                
                <ul class="space-y-1">
                    <li v-for="event in roundGroup.events" :key="event.occurred_at + '-' + event.actor_id" 
                        class="text-sm p-2 bg-white rounded shadow-sm border flex items-start gap-2"
                        :class="{
                            'border-red-300 bg-red-50': event.type === 'hit' || event.type === 'max_damage',
                            'border-orange-300 bg-orange-50': event.type === 'block_break',
                            'border-green-300 bg-green-50': event.type === 'block',
                            'border-gray-200': event.type === 'miss' || event.type === 'move',
                            'border-purple-300 bg-purple-50': event.type === 'death'
                        }">
                        <span class="text-lg leading-none font-semibold" :title="event.type">{{ getActionIcon(event.type) }}</span>
                        <div class="flex-1">
                            <span v-if="event.type === 'hit'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> hit Player <span class="font-bold">{{ event.target_id }}</span> in the {{ getZoneDisplay(event.zone) }} for <span class="text-red-600 font-bold">{{ event.damage }}</span> damage.
                            </span>
                            <span v-else-if="event.type === 'miss'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> missed their attack on Player <span class="font-bold">{{ event.target_id }}</span>.
                            </span>
                            <span v-else-if="event.type === 'block'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> blocked an attack from Player <span class="font-bold">{{ event.target_id }}</span>. <span v-if="event.damage > 0">(Took {{ event.damage }} partial damage)</span>
                            </span>
                            <span v-else-if="event.type === 'block_break'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> <span class="text-orange-600 font-bold">BROKE BLOCK</span> of Player <span class="font-bold">{{ event.target_id }}</span>! Dealt <span class="text-red-600 font-bold">{{ event.damage }}</span> damage.
                            </span>
                            <span v-else-if="event.type === 'max_damage'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> landed a <span class="text-yellow-600 font-bold">MAX DAMAGE</span> hit on Player <span class="font-bold">{{ event.target_id }}</span> for <span class="text-red-600 font-bold text-lg">{{ event.damage }}</span> damage!
                            </span>
                            <span v-else-if="event.type === 'move'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> moved.
                            </span>
                            <span v-else-if="event.type === 'death'">
                                Player <span class="font-bold">{{ event.actor_id }}</span> has fallen in battle!
                            </span>
                            <span v-else class="text-gray-500">Unknown event type: {{ event.type }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
