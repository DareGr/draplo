import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Add auth token to all requests
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Auto dev-login if no token (local dev only)
let loginPromise = null;

export async function ensureAuth() {
    const token = localStorage.getItem('auth_token');
    if (token) return token;

    if (!loginPromise) {
        loginPromise = fetch('/dev/login')
            .then(res => res.json())
            .then(data => {
                localStorage.setItem('auth_token', data.token);
                return data.token;
            });
    }

    return loginPromise;
}

export default api;
