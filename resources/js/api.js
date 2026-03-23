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

// Ensure user is authenticated
export async function ensureAuth() {
    const token = localStorage.getItem('auth_token');
    if (token) return token;

    // No token — redirect to GitHub OAuth
    window.location.href = '/auth/github';
    return null;
}

export default api;
