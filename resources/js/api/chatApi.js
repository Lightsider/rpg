import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
});

export const sendChatMessage = async (payload) => {
    const response = await api.post('/chat/send', payload);
    return response.data;
};

export const getChatState = async (chatType, contextId) => {
    const response = await api.get('/chat/state', { params: { chatType, contextId } });
    return response.data;
};

export const getPrivateChats = async () => {
    const response = await api.get('/chat/private');
    return response.data;
};

export const getParticipants = async (chatType, contextId) => {
    const response = await api.get('/chat/participants', { params: { chatType, contextId } });
    return response.data;
};
