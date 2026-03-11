<script setup>
import BlockSelector from '@/Components/Fight/BlockSelector.vue';

const props = defineProps({
    selectedTile: { type: Object, default: null },
    selectedBlocks: { type: Array, default: () => [] },
    apAvailable: { type: Number, default: 3 },
    actionCost: { type: Number, default: 0 },
    canSubmit: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }
});

const emit = defineEmits(['updateBlocks', 'submitAction']);

const updateBlocks = (blocks) => {
    emit('updateBlocks', blocks);
};

const submit = () => {
    if (!props.canSubmit || props.disabled) return;
    emit('submitAction');
};
</script>

<template>
    <div class="bg-white rounded-lg p-4 shadow-sm border space-y-4">
        <div>
            <div class="text-sm text-gray-600">Selected move</div>
            <div class="text-lg font-semibold">
                <span v-if="selectedTile">({{ selectedTile.x }}, {{ selectedTile.y }})</span>
                <span v-else class="text-gray-400">No tile selected</span>
            </div>
        </div>

        <div>
            <div class="text-sm text-gray-600 mb-2">Block zones</div>
            <BlockSelector :modelValue="selectedBlocks" @update:modelValue="updateBlocks" />
        </div>

        <div class="flex items-center justify-between text-sm text-gray-600">
            <span>AP: {{ apAvailable }}</span>
            <span>Cost: {{ actionCost }}</span>
        </div>

        <button
            type="button"
            class="w-full py-2 rounded-md text-white font-semibold"
            :class="canSubmit && !disabled ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
            :disabled="!canSubmit || disabled"
            @click="submit"
        >
            Submit Action
        </button>
    </div>
</template>
