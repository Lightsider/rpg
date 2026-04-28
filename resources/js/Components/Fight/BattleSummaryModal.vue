<script setup>
import { computed } from 'vue';
import CurrencyDisplay from '@/Components/CurrencyDisplay.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    battle: {
        type: Object,
        required: true
    },
    myCharacterId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['close', 'closeAndReturn']);

const myReward = computed(() => {
    return props.battle?.rewards?.[props.myCharacterId] || { xp: 0, copper: 0, items: [] };
});

const myTeam = computed(() => {
    const me = props.battle?.participants?.find(p => p.character_id === props.myCharacterId);
    return me?.team || null;
});

const isVictory = computed(() => {
    if (!props.battle?.winning_team) return false;
    return props.battle.winning_team === myTeam.value;
});

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-md animate-in fade-in duration-700"></div>

        <!-- Modal Content -->
        <div 
            class="relative w-full max-w-lg bg-white/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] border-t border-white/20 overflow-hidden transform transition-all animate-in zoom-in-95 slide-in-from-bottom-10 duration-500"
            :class="isVictory ? 'ring-4 ring-yellow-400/50' : 'ring-4 ring-red-500/30'"
        >
            <!-- Header with Gradient Background -->
            <div 
                class="relative h-48 flex flex-col items-center justify-center text-white text-center p-8 overflow-hidden"
                :class="isVictory ? 'bg-gradient-to-br from-yellow-400 via-amber-500 to-orange-600' : 'bg-gradient-to-br from-gray-700 via-gray-800 to-red-900'"
            >
                <!-- Decorative Elements -->
                <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                    <svg width="100%" height="100%" fill="none" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <path d="M0 100 L100 0 L100 100 Z" fill="white" />
                    </svg>
                </div>

                <div v-if="isVictory" class="relative">
                    <div class="animate-bounce">
                        <svg class="h-16 w-16 text-white mb-2 filter drop-shadow-lg" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                    </div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter italic drop-shadow-md">Victory!</h2>
                </div>
                <div v-else class="relative">
                    <svg class="h-16 w-16 text-white/80 mb-2 filter drop-shadow-lg mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="text-4xl font-black uppercase tracking-tighter italic drop-shadow-md">Defeat</h2>
                </div>
                <p class="text-white/70 text-xs font-bold uppercase tracking-[0.2em] mt-2">Battle #{{ battle.id }} Concluded</p>
            </div>

            <!-- Body -->
            <div class="p-8">
                <!-- Rewards Section -->
                <div class="mb-8">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span>Rewards & Gains</span>
                        <div class="h-px flex-1 bg-gray-100"></div>
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- XP Gain -->
                        <div class="relative group">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl blur opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                            <div class="relative bg-white border border-gray-100 p-4 rounded-xl shadow-sm flex flex-col items-center">
                                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600 mb-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Experience</div>
                                <div class="text-2xl font-black text-indigo-600">+{{ myReward.xp }}</div>
                            </div>
                        </div>

                        <!-- Copper Gain -->
                        <div class="relative group">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-yellow-400 to-amber-600 rounded-xl blur opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                            <div class="relative bg-white border border-gray-100 p-4 rounded-xl shadow-sm flex flex-col items-center">
                                <div class="p-2 bg-yellow-50 rounded-lg text-yellow-600 mb-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Copper</div>
                                <div class="text-2xl font-black text-yellow-600">+{{ myReward.copper }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Found (if any) -->
                    <div v-if="myReward.items?.length" class="mt-4 bg-gray-50 border border-dashed rounded-xl p-4">
                        <div class="text-[10px] font-bold text-gray-400 uppercase mb-2">Items Discovered</div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="itemId in myReward.items" :key="itemId" class="px-3 py-1.5 bg-white text-gray-700 text-xs font-bold rounded-lg border shadow-sm flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                Item #{{ itemId }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex flex-col gap-3">
                    <button 
                        @click="handleClose"
                        class="group relative w-full py-4 bg-gray-900 rounded-xl overflow-hidden transition-all hover:scale-[1.02] active:scale-[0.98]"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <span class="relative text-white font-black uppercase tracking-widest text-sm">Review Logs</span>
                    </button>
                    
                    <button 
                        @click="emit('closeAndReturn')"
                        class="w-full py-3 bg-white text-gray-500 font-bold uppercase tracking-widest text-[10px] hover:text-gray-900 transition-colors"
                    >
                        Return to Lobby
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation-duration: 0.5s;
    animation-fill-mode: both;
}

@keyframes zoom-in-95 {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes slide-in-from-bottom-10 {
    from {
        transform: translateY(10%);
    }
    to {
        transform: translateY(0);
    }
}

.zoom-in-95 {
    animation-name: zoom-in-95;
}

.slide-in-from-bottom-10 {
    animation-name: slide-in-from-bottom-10;
}
</style>
