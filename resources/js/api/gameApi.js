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

export const createFight = async (payload = {}) => {
    const response = await api.post('/fights', payload);
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

export const getLocations = async () => {
    const response = await api.get('/locations');
    return response.data;
};

export const changeLocation = async (locationId) => {
    const response = await api.post(`/locations/${locationId}/enter`);
    return response.data;
};

export const getCharacterLoadout = async () => {
    const response = await api.get('/character/loadout');
    return response.data;
};

export const updateCharacterLoadout = async (payload) => {
    const response = await api.put('/character/loadout', payload);
    return response.data;
};

export const equipBackpackItem = async (payload) => {
    const response = await api.post('/character/backpack/equip', payload);
    return response.data;
};

export const unequipBackpackItem = async (payload) => {
    const response = await api.post('/character/backpack/unequip', payload);
    return response.data;
};

export const openStore = async (storeId) => {
    const response = await api.post(`/store/${storeId}/open`);
    return response.data;
};

export const buyStoreItem = async (storeItemId) => {
    const response = await api.post('/store/buy', { store_item_id: storeItemId });
    return response.data;
};
