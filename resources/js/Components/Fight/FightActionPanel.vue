<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    apAvailable: {
        type: Number,
        default: 3
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['submitActions']);

const queue = ref([]);

const MAX_ACTIONS = 3;
const MAX_ATTACKS = 2;

const availableZones = [
    { value: 'head', label: 'Head' },
    { value: 'torso', label: 'Torso' },
    { value: 'left_arm', label: 'Left Arm' },
    { value: 'right_arm', label: 'Right Arm' },
    { value: 'legs', label: 'Legs' }
];

const actionType = ref('attack');
const targetZone = ref('torso');

const currentAttacksInQueue = computed(() => {
    return queue.value.filter(a => a.type === 'attack').length;
});

const canAddAttack = computed(() => {
    return currentAttacksInQueue.value < MAX_ATTACKS && queue.value.length < props.apAvailable;
});

const canAddBlock = computed(() => {
    return queue.value.length < props.apAvailable;
});

const addActionToQueue = () => {
    if (queue.value.length >= props.apAvailable) return;
    
    if (actionType.value === 'attack' && !canAddAttack.value) {
        alert("Maximum 2 attacks allowed per round.");
        return;
    }

    queue.value.push({
        id: Date.now() + Math.random(),
        type: actionType.value,
        zone: targetZone.value
    });
};

const randomZone = () => {
    const index = Math.floor(Math.random() * availableZones.length);
    return availableZones[index].value;
};

const fillRandomQueue = (attackCountTarget) => {
    if (props.apAvailable <= 0) return;

    const maxActions = Math.min(MAX_ACTIONS, props.apAvailable);
    const attackCount = Math.min(attackCountTarget, MAX_ATTACKS, maxActions);
    const blockCount = Math.max(0, maxActions - attackCount);

    const nextQueue = [];
    for (let i = 0; i < attackCount; i++) {
        nextQueue.push({
            id: Date.now() + Math.random(),
            type: 'attack',
            zone: randomZone()
        });
    }

    const availableBlockZones = availableZones.map(zone => zone.value);
    for (let i = 0; i < blockCount; i++) {
        if (availableBlockZones.length === 0) break;
        const index = Math.floor(Math.random() * availableBlockZones.length);
        const zone = availableBlockZones.splice(index, 1)[0];
        nextQueue.push({
            id: Date.now() + Math.random(),
            type: 'block',
            zone
        });
    }

    queue.value = nextQueue;
};

const randomAttackPattern = () => {
    fillRandomQueue(2);
};

const randomDefensePattern = () => {
    fillRandomQueue(1);
};

const removeAction = (index) => {
    queue.value.splice(index, 1);
};

const clearQueue = () => {
    queue.value = [];
};

const submitQueue = () => {
    if (queue.value.length === 0) {
        if (!confirm("Submit an empty round (do nothing)?")) {
            return;
        }
    }
    
    // Transform queue to API format
    const payload = queue.value.map(a => ({
        type: a.type,
        zone: a.zone
    }));
    
    emit('submitActions', payload);
};

// Expose clearQueue so parent can reset it after successful submission
defineExpose({
    clearQueue
});
</script>

<template>
    <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
        <h3 class="font-bold text-lg mb-4 flex justify-between items-center">
            <span>Action Queue</span>
            <span class="text-sm font-normal bg-blue-100 text-blue-800 py-1 px-2 rounded-full">
                AP Available: {{ apAvailable - queue.length }} / {{ apAvailable }}
            </span>
        </h3>

        <!-- Builder Controls -->
        <div class="space-y-4 mb-6" :class="{ 'opacity-50 pointer-events-none': disabled }">
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                    <select v-model="actionType" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 min-h-[42px] px-3 border bg-white cursor-pointer select-none ring-0 outline-none hover:bg-gray-50 transition-colors">
                        <option value="attack" :disabled="!canAddAttack">Attack (Melee)</option>
                        <option value="block" :disabled="!canAddBlock">Block (Defend)</option>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Zone</label>
                    <select v-model="targetZone" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 min-h-[42px] px-3 border bg-white cursor-pointer select-none ring-0 outline-none hover:bg-gray-50 transition-colors">
                        <option v-for="zone in availableZones" :key="zone.value" :value="zone.value">
                            {{ zone.label }}
                        </option>
                    </select>
                </div>
            </div>

            <button 
                @click="addActionToQueue"
                :disabled="queue.length >= apAvailable"
                class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Add to Queue
            </button>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    @click="randomAttackPattern"
                    :disabled="disabled"
                    class="w-full bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 font-semibold py-2 px-4 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Random Attack (2 atk + block)
                </button>
                <button
                    @click="randomDefensePattern"
                    :disabled="disabled"
                    class="w-full bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 font-semibold py-2 px-4 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Random Defense (1 atk + block)
                </button>
            </div>
        </div>

        <!-- Current Queue Display -->
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Current Queue</h4>
            
            <div v-if="queue.length === 0" class="text-center py-4 bg-gray-50 border border-dashed rounded text-gray-500 text-sm">
                Queue is empty.
            </div>
            
            <ul v-else class="space-y-2">
                <li v-for="(action, index) in queue" :key="action.id" class="flex justify-between items-center bg-gray-50 p-3 rounded border">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-gray-500 w-6">{{ index + 1 }}.</span>
                        <span class="capitalize font-semibold" :class="action.type === 'attack' ? 'text-red-700' : 'text-blue-700'">{{ action.type }}</span>
                        <span class="text-gray-600 capitalize">-> {{ action.zone.replace('_', ' ') }}</span>
                    </div>
                    <button @click="removeAction(index)" class="text-red-500 hover:text-red-700" :disabled="disabled">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </li>
            </ul>
        </div>

        <div class="flex gap-4">
            <button 
                @click="clearQueue"
                :disabled="queue.length === 0 || disabled"
                class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
             >
                Clear
            </button>
            <button 
                @click="submitQueue"
                :disabled="disabled"
                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Submit Round Actions
            </button>
        </div>
    </div>
</template>

