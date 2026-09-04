/**
 * COLM Registrar Document Request and Tracking System
 * API Client Engine (Fetch API Wrapper)
 */

const API_BASE = (function() {
    const loc = window.location;
    const frontendMarker = '/frontend/';
    const rootPath = loc.pathname.includes(frontendMarker)
        ? loc.pathname.split(frontendMarker)[0]
        : loc.pathname.replace(/\/[^/]*$/, '');

    return `${loc.origin}${rootPath}/api/v1`;
})();

let currentCsrfToken = localStorage.getItem('colm_csrf_token') || '';

const api = {
    setCsrfToken(token) {
        currentCsrfToken = token;
        localStorage.setItem('colm_csrf_token', token);
    },

    getCsrfToken() {
        return currentCsrfToken;
    },

    async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
        
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (currentCsrfToken) {
            headers['X-CSRF-Token'] = currentCsrfToken;
        }

        // Only set Content-Type to application/json if not FormData
        if (!(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        const config = {
            ...options,
            headers,
            credentials: 'same-origin'
        };

        try {
            const res = await fetch(url, config);
            const data = await res.json();

            // Extract CSRF token if returned in response data
            if (data?.data?.csrf_token) {
                this.setCsrfToken(data.data.csrf_token);
            }

            if (!res.ok || data.success === false) {
                // If 401 unauthenticated and not on login page or public tracking, redirect to login
                if (res.status === 401 && !window.location.pathname.includes('login.html') && !window.location.pathname.includes('index.html')) {
                    window.location.href = 'login.html';
                }
                const error = new Error(data.message || 'An error occurred during API request.');
                error.status = res.status;
                error.errors = data.errors || null;
                error.errorCode = data.error_code || null;
                throw error;
            }

            return data;
        } catch (err) {
            console.error(`[API ERROR] ${endpoint}:`, err);
            throw err;
        }
    },

    get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },

    post(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: body instanceof FormData ? body : JSON.stringify(body)
        });
    },

    put(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    },

    patch(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(body)
        });
    },

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    upload(endpoint, formData) {
        return this.request(endpoint, {
            method: 'POST',
            body: formData
        });
    }
};

window.api = api;
