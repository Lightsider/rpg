import axios from 'axios';

// The default axios instance in Laravel is usually configured in bootstrap.js
// to handle CSRF tokens and X-Requested-With headers. We'll use the global instance.
const api = axios.create({
    baseURL: '/api',
});

export const getGameState = async () => {
    const response = await api.get('/game');
    return response.data;
};

export const getAvailableFights = async () => {
    const response = await api.get('/fights');
    return response.data;
};

export const createFight = async () => {
    const response = await api.post('/fights');
    return response.data;
};

export const joinFight = async (fightId) => {
    const response = await api.post(`/fights/${fightId}/join`);
    return response.data;
};

export const cancelFight = async (fightId) => {
    const response = await api.post(`/fights/${fightId}/cancel`);
    return response.data;
};

export const getFightState = async (fightId) => {
    const response = await api.get(`/fights/${fightId}`);
    return response.data;
};

export const submitActions = async (fightId, actions) => {
    const response = await api.post(`/fights/${fightId}/actions`, { actions });
    return response.data;
};

export const getFightLogs = async (fightId) => {
    const response = await api.get(`/fights/${fightId}/log`);
    return response.data;
};
