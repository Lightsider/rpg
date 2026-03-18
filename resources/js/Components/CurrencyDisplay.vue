<template>
    <div class="flex items-center space-x-3 bg-gray-900/50 p-2 rounded-lg border border-gray-700 backdrop-blur-sm">
        <div v-if="split.gold > 0" class="flex items-center">
            <span class="text-yellow-400 font-bold mr-1">{{ split.gold }}</span>
            <span class="text-xs text-yellow-600 uppercase tracking-tighter">Gold</span>
        </div>
        <div v-if="split.silver > 0 || split.gold > 0" class="flex items-center">
            <span class="text-gray-300 font-bold mr-1">{{ split.silver }}</span>
            <span class="text-xs text-gray-500 uppercase tracking-tighter">Silver</span>
        </div>
        <div class="flex items-center">
            <span class="text-orange-400 font-bold mr-1">{{ split.copper }}</span>
            <span class="text-xs text-orange-700 uppercase tracking-tighter">Copper</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    copper: {
        type: Number,
        required: true,
        default: 0
    }
});

const split = computed(() => {
    const gold = Math.floor(props.copper / 100);
    const remaining = props.copper % 100;
    const silver = Math.floor(remaining / 10);
    const copper = remaining % 10;

    return { gold, silver, copper };
});
</script>
