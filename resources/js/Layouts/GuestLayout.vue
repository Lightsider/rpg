<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-gray-950 pt-6 sm:pt-0 overflow-hidden font-sans">
        
        <!-- Animated Background Effects -->
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-900/20 via-gray-950 to-gray-950 z-0"></div>
        <div class="absolute w-[800px] h-[800px] bg-purple-600/10 blur-[100px] rounded-full -top-40 -left-20 animate-pulse transition-opacity duration-[10000ms]"></div>
        <div class="absolute w-[600px] h-[600px] bg-indigo-600/10 blur-[100px] rounded-full bottom-0 right-0 animate-pulse animation-delay-2000 transition-opacity duration-[8000ms]"></div>

        <div class="relative z-10">
            <Link href="/">
                <h1 class="text-5xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-indigo-400 drop-shadow-lg">
                    AETHELIA
                </h1>
                <p class="text-indigo-300/60 text-center tracking-widest text-xs mt-1 uppercase font-semibold">Realms of Ruin</p>
            </Link>
        </div>

        <div
            class="relative z-10 mt-10 w-full overflow-hidden bg-gray-900/60 backdrop-blur-xl px-10 py-12 shadow-2xl sm:max-w-md sm:rounded-2xl border border-white/10 ring-1 ring-white/5"
        >
            <slot />
        </div>
    </div>
</template>
