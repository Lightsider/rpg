<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Your Hero" />

        <div class="mb-8 text-center">
            <h2 class="text-2xl font-bold text-white tracking-wide">Enter the Realm</h2>
            <p class="text-sm text-indigo-300/70 mt-1">Forge your legacy and prepare for battle</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5 relative z-20">
            <div>
                <label for="name" class="block text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Character Name</label>
                <input
                    id="name"
                    type="text"
                    class="block w-full bg-gray-950/50 border border-indigo-500/30 rounded-lg shadow-sm py-2.5 px-3 text-indigo-100 placeholder-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all duration-300"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="E.g., Arthas"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Email Address</label>
                <input
                    id="email"
                    type="email"
                    class="block w-full bg-gray-950/50 border border-indigo-500/30 rounded-lg shadow-sm py-2.5 px-3 text-indigo-100 placeholder-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all duration-300"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="hero@aethelia.com"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Secret Passage</label>
                <input
                    id="password"
                    type="password"
                    class="block w-full bg-gray-950/50 border border-indigo-500/30 rounded-lg shadow-sm py-2.5 px-3 text-indigo-100 placeholder-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all duration-300"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Confirm Passage</label>
                <input
                    id="password_confirmation"
                    type="password"
                    class="block w-full bg-gray-950/50 border border-indigo-500/30 rounded-lg shadow-sm py-2.5 px-3 text-indigo-100 placeholder-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all duration-300"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-4 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm font-medium text-indigo-400 hover:text-purple-300 transition-colors"
                >
                    Already a veteran?
                </Link>

                <button
                    type="submit"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                    class="relative inline-flex items-center justify-center px-6 py-2.5 overflow-hidden font-bold text-white rounded-lg shadow-2xl group focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900"
                >
                    <span class="absolute inset-0 w-full h-full -mt-1 rounded-lg opacity-30 bg-gradient-to-b from-transparent via-transparent to-black"></span>
                    <span class="absolute inset-0 w-full h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg group-hover:from-indigo-400 group-hover:to-purple-500 transition-all duration-300"></span>
                    <span class="relative">Awaken</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
