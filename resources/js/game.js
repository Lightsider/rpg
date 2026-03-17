/**
 * Game WebSocket helper.
 * This class handles the raw WebSocket connection to port 8081.
 */
export class GameSocket {
    constructor(port = 8081) {
        this.port = port;
        this.socket = null;
        this.handlers = new Map();
        this.reconnectTimeout = null;
    }

    connect() {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) return;

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        this.socket = new WebSocket(`${protocol}//${window.location.hostname}:${this.port}`);

        this.socket.onopen = () => {
            console.log('[GS] Connected');
            this.emit('connected', {});
        };

        this.socket.onmessage = (event) => {
            try {
                const message = JSON.parse(event.data);
                console.log('[GS] Msg:', message.type);
                this.emit(message.type, message.payload);
            } catch (e) {
                console.error('[GS] Parse error', e);
            }
        };

        this.socket.onclose = () => {
            console.log('[GS] Disconnected');
            this.emit('disconnected', {});
            this.scheduleReconnect();
        };

        this.socket.onerror = (err) => {
            console.error('[GS] Error', err);
        };
    }

    scheduleReconnect() {
        if (this.reconnectTimeout) clearTimeout(this.reconnectTimeout);
        this.reconnectTimeout = setTimeout(() => this.connect(), 3000);
    }

    send(type, payload = {}) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({
                id: Date.now(),
                type,
                payload,
                timestamp: Date.now()
            }));
        } else {
            console.warn('[GS] Cannot send: Socket not ready');
        }
    }

    on(type, callback) {
        if (!this.handlers.has(type)) {
            this.handlers.set(type, []);
        }
        this.handlers.get(type).push(callback);
    }

    off(type, callback) {
        if (!this.handlers.has(type)) return;
        const list = this.handlers.get(type);
        const index = list.indexOf(callback);
        if (index !== -1) list.splice(index, 1);
    }

    emit(type, payload) {
        if (this.handlers.has(type)) {
            this.handlers.get(type).forEach(cb => cb(payload));
        }
    }
}

export const gameSocket = new GameSocket();
window.gameSocket = gameSocket; // Keep for compatibility if needed elsewhere
console.log('game.js (WS Helper) loaded');
