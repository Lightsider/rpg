<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { sendChatMessage, getChatState, getPrivateChats, getParticipants } from '@/api/chatApi';

const props = defineProps({
    character: { type: Object, required: true },
    location: { type: Object, required: false, default: null },
    currentFight: { type: Object, required: false, default: null },
});

const activeTab = ref('location');
const activeChat = ref({ type: 'location', contextId: null, chatId: null });
const messages = ref([]);
const participants = ref([]);
const privateChats = ref([]);
const locationParticipants = ref([]);
const battleParticipants = ref([]);
const unread = ref({});
const newMessage = ref('');
const dmTargetId = ref('');
const messageList = ref(null);

const scrollToBottom = async () => {
    await nextTick();
    if (messageList.value) {
        messageList.value.scrollTop = messageList.value.scrollHeight;
    }
};

const refreshParticipants = async () => {
    if (!activeChat.value.contextId) return;
    try {
        const list = await getParticipants(activeChat.value.type, activeChat.value.contextId);
        participants.value = list;
        if (activeChat.value.type === 'location') locationParticipants.value = list;
        if (activeChat.value.type === 'battle') battleParticipants.value = list;
    } catch (e) {
        console.error(e);
    }
};

const locationId = computed(() => props.location?.id || null);
const battleId = computed(() => props.currentFight?.id || null);

const chatKey = (type, contextId, chatId) => {
    if (type === 'private') {
        return `private:${chatId ?? contextId ?? 'new'}`;
    }
    return `${type}:${contextId ?? 'none'}`;
};

const channels = new Map();

const subscribe = (channelName) => {
    if (!channelName || channels.has(channelName) || !window.Echo) return;
    const channel = window.Echo.private(channelName)
        .listen('.chat.message', (payload) => handleIncoming(payload?.payload ?? payload));
    channels.set(channelName, channel);
};

const unsubscribe = (channelName) => {
    if (!channelName || !channels.has(channelName) || !window.Echo) return;
    window.Echo.leave(channelName);
    channels.delete(channelName);
};

const clearAllChannels = () => {
    for (const name of channels.keys()) {
        unsubscribe(name);
    }
};

const handleIncoming = (data) => {
    if (!data) return;
    const key = chatKey(data.chatType, data.contextId, data.chatId);

    const isPrivate = data.chatType === 'private';
    const matchesActive = isPrivate
        ? (activeChat.value.type === 'private'
            && ((activeChat.value.chatId && activeChat.value.chatId == data.chatId)
                || (!activeChat.value.chatId && activeChat.value.contextId == data.sender?.id)))
        : (activeChat.value.type === data.chatType && activeChat.value.contextId == data.contextId);

    if (matchesActive) {
        if (isPrivate && !activeChat.value.chatId && data.chatId) {
            activeChat.value.chatId = data.chatId;
            subscribe(`chat.private.${data.chatId}`);
        }
        
        // Deduplicate
        const exists = messages.value.some(m => m.id == data.messageId);
        if (!exists) {
            messages.value.push({
                id: data.messageId,
                chat_id: data.chatId,
                sender_id: data.sender?.id,
                message: data.message,
                created_at: data.timestamp,
            });
            scrollToBottom();
        }

        // Add sender to participants if missing
        if (data.sender?.id && !participants.value.some(p => p.id == data.sender.id)) {
            participants.value.push({ id: data.sender.id, name: data.sender.name });
        }
    } else {
        unread.value[key] = (unread.value[key] || 0) + 1;
    }

    if (data.chatType === 'private') {
        const existing = privateChats.value.find(c => c.chat_id === data.chatId);
        if (!existing && data.sender?.id && data.sender?.id !== props.character?.id) {
            privateChats.value.unshift({
                chat_id: data.chatId,
                participant: { id: data.sender.id, name: data.sender.name },
            });
            subscribe(`chat.private.${data.chatId}`);
        }
    }
};

const loadPrivateChats = async () => {
    try {
        privateChats.value = await getPrivateChats();
        privateChats.value.forEach(chat => {
            subscribe(`chat.private.${chat.chat_id}`);
        });
    } catch (e) {
        console.error(e);
    }
};

const openChat = async (type, contextId) => {
    if (!contextId) return;
    try {
        const state = await getChatState(type, contextId);
        activeTab.value = type;
        activeChat.value = {
            type,
            contextId,
            chatId: state.chat_id ?? null,
        };
        messages.value = state.messages || [];
        participants.value = state.participants || [];

        const key = chatKey(type, contextId, state.chat_id);
        unread.value[key] = 0;

        if (type === 'location') {
            locationParticipants.value = state.participants || [];
            subscribe(`chat.location.${contextId}`);
        } else if (type === 'battle') {
            battleParticipants.value = state.participants || [];
            subscribe(`chat.battle.${contextId}`);
        } else if (type === 'private' && state.chat_id) {
            subscribe(`chat.private.${state.chat_id}`);
        }
        scrollToBottom();
    } catch (e) {
        console.error(e);
    }
};

const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text) return;
    if (!activeChat.value.contextId) return;
    
    try {
        const payload = {
            chatType: activeChat.value.type,
            contextId: activeChat.value.contextId,
            message: text,
        };
        
        const response = await sendChatMessage(payload);
        
        // Handle new private chat session link
        if (activeChat.value.type === 'private' && !activeChat.value.chatId && response.chat_id) {
            activeChat.value.chatId = response.chat_id;
            subscribe(`chat.private.${response.chat_id}`);
            const targetId = activeChat.value.contextId;
            const existing = privateChats.value.find(c => c.participant.id == targetId);
            if (!existing) {
                const other = participants.value.find(p => p.id == targetId);
                if (other) {
                    privateChats.value.unshift({ chat_id: response.chat_id, participant: other });
                }
            }
        }

        // Push locally and clear
        if (response.message) {
            const exists = messages.value.some(m => m.id == response.message.id);
            if (!exists) {
                messages.value.push(response.message);
            }
        }
        newMessage.value = '';
        scrollToBottom();
    } catch (e) {
        console.error('Send failed:', e);
        // Fallback: clear input anyway if user sees the message arrived (it might arrive via broadcast anyway)
    }
};

const startDm = async () => {
    if (!dmTargetId.value) return;
    await openChat('private', Number(dmTargetId.value));
    dmTargetId.value = '';
};

watch(locationId, (newId) => {
    if (newId) {
        subscribe(`chat.location.${newId}`);
        if (!activeChat.value.contextId || activeChat.value.type === 'location') {
            openChat('location', newId);
        }
    }
});

watch(battleId, (newId) => {
    if (newId) {
        subscribe(`chat.battle.${newId}`);
        if (activeChat.value.type === 'battle') {
            openChat('battle', newId);
        }
    }
});

onMounted(async () => {
    await loadPrivateChats();
    if (locationId.value) {
        await openChat('location', locationId.value);
    }
});

onUnmounted(() => {
    clearAllChannels();
});

const unreadFor = (type, contextId, chatId) => {
    const key = chatKey(type, contextId, chatId);
    return unread.value[key] || 0;
};

const dmTargets = computed(() => {
    const base = locationParticipants.value.length ? locationParticipants.value : participants.value;
    return base.filter(p => p.id !== props.character?.id);
});
</script>

<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full flex flex-col">
        <div class="p-4 border-b">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold">Chat</h3>
                <div class="text-xs text-gray-500">{{ props.character?.name }}</div>
            </div>
            <div class="mt-3 flex gap-2 text-xs">
                <button
                    class="px-3 py-1 rounded border"
                    :class="activeTab === 'location' ? 'bg-gray-900 text-white border-gray-900' : 'bg-gray-50 text-gray-700'"
                    @click="openChat('location', locationId)"
                >
                    Location
                    <span v-if="unreadFor('location', locationId)" class="ml-1 text-[10px] bg-red-600 text-white px-1.5 rounded">
                        {{ unreadFor('location', locationId) }}
                    </span>
                </button>
                <button
                    v-if="battleId"
                    class="px-3 py-1 rounded border"
                    :class="activeTab === 'battle' ? 'bg-gray-900 text-white border-gray-900' : 'bg-gray-50 text-gray-700'"
                    @click="openChat('battle', battleId)"
                >
                    Battle
                    <span v-if="unreadFor('battle', battleId)" class="ml-1 text-[10px] bg-red-600 text-white px-1.5 rounded">
                        {{ unreadFor('battle', battleId) }}
                    </span>
                </button>
                <button
                    class="px-3 py-1 rounded border"
                    :class="activeTab === 'private' ? 'bg-gray-900 text-white border-gray-900' : 'bg-gray-50 text-gray-700'"
                    @click="activeTab = 'private'"
                >
                    DMs
                    <span v-if="Object.keys(unread).some(k => k.startsWith('private:') && unread[k])" class="ml-1 text-[10px] bg-red-600 text-white px-1.5 rounded">
                        !
                    </span>
                </button>
            </div>
        </div>

        <div ref="messageList" class="flex-1 overflow-y-auto p-4 space-y-4">
            <div v-if="activeTab === 'private'" class="space-y-3">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Dialogs</div>
                    <div v-if="privateChats.length === 0" class="text-sm text-gray-500 mt-2">No private chats yet.</div>
                    <div v-else class="space-y-2 mt-2">
                        <button
                            v-for="chat in privateChats"
                            :key="chat.chat_id"
                            class="w-full text-left p-2 border rounded hover:bg-gray-50"
                            @click="openChat('private', chat.participant.id)"
                        >
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-sm">{{ chat.participant.name }}</span>
                                <span v-if="unreadFor('private', chat.participant.id, chat.chat_id)" class="text-[10px] bg-red-600 text-white px-1.5 rounded">
                                    {{ unreadFor('private', chat.participant.id, chat.chat_id) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="pt-3 border-t">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Start DM</div>
                    <div class="flex gap-2">
                        <select v-model="dmTargetId" class="flex-1 border rounded text-sm px-2 py-1">
                            <option value="">Select player</option>
                            <option v-for="p in dmTargets" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                        <button @click="startDm" class="px-3 py-1 bg-gray-900 text-white text-xs rounded">Open</button>
                    </div>
                </div>

                <div v-if="activeChat.type === 'private' && activeChat.contextId" class="pt-3 border-t space-y-2">
                    <div v-if="messages.length === 0" class="text-sm text-gray-500">No messages yet.</div>
                    <div v-for="msg in messages" :key="msg.id + '-' + msg.created_at" class="text-sm">
                        <span class="font-semibold">{{ participants.find(p => p.id === msg.sender_id)?.name || 'Unknown' }}</span>:
                        <span class="text-gray-700">{{ msg.message }}</span>
                        <span class="text-[10px] text-gray-400 ml-2">{{ new Date(msg.created_at).toLocaleTimeString() }}</span>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-2">
                <div v-if="messages.length === 0" class="text-sm text-gray-500">No messages yet.</div>
                <div v-for="msg in messages" :key="msg.id + '-' + msg.created_at" class="text-sm">
                    <span class="font-semibold">{{ participants.find(p => p.id == msg.sender_id)?.name || 'Unknown' }}</span>:
                    <span class="text-gray-700">{{ msg.message }}</span>
                    <span class="text-[10px] text-gray-400 ml-2">{{ new Date(msg.created_at).toLocaleTimeString() }}</span>
                </div>
            </div>
        </div>

        <div class="p-4 border-t" v-if="activeTab !== 'private'">
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Participants</div>
            <div class="flex flex-wrap gap-2 mb-3">
                <span v-for="p in participants" :key="p.id" class="text-xs bg-gray-100 px-2 py-1 rounded">
                    {{ p.name }}
                </span>
            </div>
            <div class="flex gap-2">
                <input v-model="newMessage" type="text" class="flex-1 border rounded px-2 py-1 text-sm" placeholder="Type a message..." @keyup.enter="sendMessage" />
                <button @click="sendMessage" class="px-3 py-1 bg-gray-900 text-white text-sm rounded">Send</button>
            </div>
        </div>

        <div class="p-4 border-t" v-else>
            <div v-if="activeChat.type === 'private' && activeChat.contextId" class="flex gap-2">
                <input v-model="newMessage" type="text" class="flex-1 border rounded px-2 py-1 text-sm" placeholder="Type a message..." @keyup.enter="sendMessage" />
                <button @click="sendMessage" class="px-3 py-1 bg-gray-900 text-white text-sm rounded">Send</button>
            </div>
        </div>
    </div>
</template>
