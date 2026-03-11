<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:modelValue']);

const zones = [
    { key: 'head', label: 'HEAD' },
    { key: 'torso', label: 'TORSO' },
    { key: 'legs', label: 'LEGS' },
    { key: 'left_arm', label: 'LEFT ARM' },
    { key: 'right_arm', label: 'RIGHT ARM' }
];

const toggleZone = (zone) => {
    const next = props.modelValue.includes(zone)
        ? props.modelValue.filter(z => z !== zone)
        : [...props.modelValue, zone];
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="block-selector">
        <button
            v-for="zone in zones"
            :key="zone.key"
            type="button"
            class="zone-btn"
            :class="{ active: modelValue.includes(zone.key) }"
            @click="toggleZone(zone.key)"
        >
            {{ zone.label }}
        </button>
    </div>
</template>

<style scoped>
.block-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.zone-btn {
    border: 1px solid #d1d5db;
    background: #fff;
    color: #374151;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.zone-btn.active {
    background: #1d4ed8;
    color: #fff;
    border-color: #1d4ed8;
}
</style>
