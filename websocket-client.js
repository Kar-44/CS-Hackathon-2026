// websocket-client.js
class WebSocketClient {
    constructor() {
        this.socket = null;
        this.connected = false;
        this.messageCallbacks = [];
        this.typingCallbacks = [];
        this.connectionCallbacks = [];
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000;
        this.currentChatId = null;
    }
    
    connect(sessionId) {
        return new Promise((resolve, reject) => {
            try {
                // Use wss:// for secure connections
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const host = window.location.hostname;
                const port = '8082'; // Match the server port
                
                this.socket = new WebSocket(`${protocol}//${host}:${port}`);
                
                this.socket.onopen = () => {
                    console.log('WebSocket connected');
                    this.connected = true;
                    this.reconnectAttempts = 0;
                    
                    // Authenticate with session ID
                    this.send({
                        type: 'auth',
                        token: sessionId
                    });
                    
                    this.connectionCallbacks.forEach(cb => cb(true));
                    resolve();
                };
                
                this.socket.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.handleMessage(data);
                    } catch (e) {
                        console.error('Error parsing message:', e);
                    }
                };
                
                this.socket.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.connected = false;
                    this.connectionCallbacks.forEach(cb => cb(false));
                    this.reconnect();
                };
                
                this.socket.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    reject(error);
                };
                
            } catch (error) {
                console.error('Connection error:', error);
                reject(error);
            }
        });
    }
    
    reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnect attempts reached');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1);
        
        console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            if (!this.connected) {
                this.connect(document.cookie.replace(/(?:(?:^|.*;\s*)PHPSESSID\s*\=\s*([^;]*).*$)|^.*$/, "$1"));
            }
        }, delay);
    }
    
    send(data) {
        if (this.socket && this.connected) {
            this.socket.send(JSON.stringify(data));
        } else {
            console.warn('WebSocket not connected');
        }
    }
    
    handleMessage(data) {
        switch (data.type) {
            case 'auth_success':
                console.log('Authentication successful');
                break;
                
            case 'auth_failed':
                console.error('Authentication failed');
                break;
                
            case 'new_message':
                this.messageCallbacks.forEach(cb => cb(data.message));
                break;
                
            case 'typing':
                this.typingCallbacks.forEach(cb => cb(data));
                break;
                
            case 'subscribed':
                console.log('Subscribed to chat:', data.chatId);
                break;
                
            case 'error':
                console.error('Server error:', data.message);
                break;
        }
    }
    
    subscribeToChat(chatId) {
        this.currentChatId = chatId;
        this.send({
            type: 'subscribe',
            chatId: chatId
        });
    }
    
    sendMessage(chatId, message) {
        this.send({
            type: 'message',
            chatId: chatId,
            message: message
        });
    }
    
    sendTyping(chatId, isTyping) {
        this.send({
            type: 'typing',
            chatId: chatId,
            isTyping: isTyping
        });
    }
    
    onMessage(callback) {
        this.messageCallbacks.push(callback);
    }
    
    onTyping(callback) {
        this.typingCallbacks.push(callback);
    }
    
    onConnectionChange(callback) {
        this.connectionCallbacks.push(callback);
    }
    
    disconnect() {
        if (this.socket) {
            this.socket.close();
        }
    }
}
