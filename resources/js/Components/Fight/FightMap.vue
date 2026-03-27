<script setup>
import { computed } from 'vue';

const props = defineProps({
    map: { type: Object, required: true },
    positions: { type: Array, required: true },
    myCharacterId: { type: Number, required: true },
    myTeam: { type: String, default: null },
    selectedTile: { type: Object, default: null }
});

const emit = defineEmits(['tileSelected']);

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.map.width}, 1fr)`
}));

const myPosition = computed(() => {
    return props.positions.find(p => p.character_id === props.myCharacterId) || null;
});

const isAdjacent = (x, y) => {
    if (!myPosition.value) return false;
    const dx = Math.abs(myPosition.value.x - x);
    const dy = Math.abs(myPosition.value.y - y);
    return dx <= 1 && dy <= 1 && (dx + dy > 0);
};

const getFighterAt = (x, y) => {
    return props.positions.find(p => p.x === x && p.y === y) || null;
};

const getTeamClass = (fighter) => {
    if (!fighter?.team) return 'fighter-neutral';
    return fighter.team === 'blue' ? 'fighter-blue' : 'fighter-red';
};

const isSelected = (x, y) => {
    return props.selectedTile && props.selectedTile.x === x && props.selectedTile.y === y;
};

const handleClick = (x, y) => {
    emit('tileSelected', { x, y });
};
</script>

<template>
    <div class="fight-map" :style="gridStyle">
        <div
            v-for="y in map.height"
            :key="`row-${y}`"
            class="row"
        >
            <div
                v-for="x in map.width"
                :key="`tile-${x}-${y}`"
                class="tile"
                :class="{
                    'tile-player': myPosition && myPosition.x === (x - 1) && myPosition.y === (y - 1),
                    'tile-adjacent': isAdjacent(x - 1, y - 1),
                    'tile-selected': isSelected(x - 1, y - 1)
                }"
                @click="handleClick(x - 1, y - 1)"
            >
                <div class="tile-coord">{{ x - 1 }},{{ y - 1 }}</div>
                <div v-if="getFighterAt(x - 1, y - 1)" class="fighter"
                     :class="[
                        getFighterAt(x - 1, y - 1).character_id === myCharacterId ? 'fighter-me' : 'fighter-enemy',
                        getTeamClass(getFighterAt(x - 1, y - 1))
                     ]">
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fight-map {
    display: grid;
    gap: 6px;
}

.row {
    display: contents;
}

.tile {
    background: #f7f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    min-height: 64px;
    position: relative;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.tile:hover {
    background: #f0f0f0;
}

.tile-player {
    border-color: #2563eb;
}

.tile-adjacent {
    background: #eef5ff;
}

.tile-selected {
    background: #dbeafe;
    border-color: #1d4ed8;
}

.tile-coord {
    position: absolute;
    top: 6px;
    left: 6px;
    font-size: 11px;
    color: #6b7280;
}

.fighter {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    position: absolute;
    right: 8px;
    bottom: 8px;
}

.fighter-me {
    border: 2px solid #1d4ed8;
}

.fighter-enemy {
    border: 2px solid #991b1b;
}

.fighter-blue {
    background: #2563eb;
}

.fighter-red {
    background: #dc2626;
}

.fighter-neutral {
    background: #6b7280;
}
</style>
