// api.js - Shared API Client for CampusCycle
// All pages include this file to communicate with the backend server.

window.API = (function () {
    const BASE = ''; // Same origin - server serves static files

    // --- Token Management ---
    function getToken() {
        return localStorage.getItem('campusToken');
    }
    function setToken(token) {
        localStorage.setItem('campusToken', token);
    }
    function clearToken() {
        localStorage.removeItem('campusToken');
    }

    // --- Current User Cache (kept in localStorage for quick access) ---
    function getCurrentUser() {
        const data = localStorage.getItem('currentUser');
        return data ? JSON.parse(data) : null;
    }
    function setCurrentUser(user) {
        localStorage.setItem('currentUser', JSON.stringify(user));
    }
    function clearCurrentUser() {
        localStorage.removeItem('currentUser');
    }

    // --- Fetch Wrapper ---
    async function apiFetch(url, options = {}) {
        const token = getToken();
        const headers = options.headers || {};
        headers['Content-Type'] = 'application/json';
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        options.headers = headers;

        const response = await fetch(BASE + url, options);
        const data = await response.json();

        if (!response.ok) {
            throw { status: response.status, ...data };
        }
        return data;
    }

    return {
        getToken,
        setToken,
        clearToken,
        getCurrentUser,
        setCurrentUser,
        clearCurrentUser,

        // --- Auth ---
        register: async function (username, email, password) {
            return apiFetch('/api/register', {
                method: 'POST',
                body: JSON.stringify({ username, email, password: btoa(password) })
            });
        },

        login: async function (username, password) {
            const data = await apiFetch('/api/login', {
                method: 'POST',
                body: JSON.stringify({ username, password: btoa(password) })
            });
            if (data.success) {
                setToken(data.token);
                setCurrentUser(data.user);
            }
            return data;
        },

        logout: function () {
            clearToken();
            clearCurrentUser();
        },

        getUsers: async function () {
            return apiFetch('/api/users');
        },

        // --- Profile ---
        updateProfile: async function (profileData) {
            const data = await apiFetch('/api/profile', {
                method: 'PUT',
                body: JSON.stringify(profileData)
            });
            if (data.success) {
                setCurrentUser(data.user);
            }
            return data;
        },

        changePassword: async function (oldPassword, newPassword) {
            const data = await apiFetch('/api/password', {
                method: 'PUT',
                body: JSON.stringify({
                    oldPassword: btoa(oldPassword),
                    newPassword: btoa(newPassword)
                })
            });
            if (data.success && data.token) {
                setToken(data.token);
                // Update cached user password
                const user = getCurrentUser();
                if (user) {
                    user.password = btoa(newPassword);
                    setCurrentUser(user);
                }
            }
            return data;
        },

        // --- Offers ---
        getOffers: async function () {
            return apiFetch('/api/offers');
        },

        addOffer: async function (offer) {
            return apiFetch('/api/offers', {
                method: 'POST',
                body: JSON.stringify(offer)
            });
        },

        updateOffer: async function (id, data) {
            return apiFetch('/api/offers/' + id, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        deleteOffer: async function (id) {
            return apiFetch('/api/offers/' + id, {
                method: 'DELETE'
            });
        },

        // --- Favorites ---
        getFavorites: async function () {
            return apiFetch('/api/favorites');
        },

        addFavorite: async function (offerId) {
            const data = await apiFetch('/api/favorites/' + offerId, {
                method: 'POST'
            });
            // Update cached user
            const user = getCurrentUser();
            if (user && data.favorites) {
                user.favorites = data.favorites;
                setCurrentUser(user);
            }
            return data;
        },

        removeFavorite: async function (offerId) {
            const data = await apiFetch('/api/favorites/' + offerId, {
                method: 'DELETE'
            });
            const user = getCurrentUser();
            if (user && data.favorites) {
                user.favorites = data.favorites;
                setCurrentUser(user);
            }
            return data;
        },

        // --- Chats ---
        getChats: async function () {
            return apiFetch('/api/chats');
        },

        createChat: async function (chatData) {
            return apiFetch('/api/chats', {
                method: 'POST',
                body: JSON.stringify(chatData)
            });
        },

        getChatMessages: async function (chatId) {
            return apiFetch('/api/chats/' + chatId + '/messages');
        },

        sendMessage: async function (chatId, text) {
            return apiFetch('/api/chats/' + chatId + '/messages', {
                method: 'POST',
                body: JSON.stringify({ text })
            });
        },

        markChatRead: async function (chatId) {
            return apiFetch('/api/chats/' + chatId + '/read', {
                method: 'PUT'
            });
        }
    };
})();
