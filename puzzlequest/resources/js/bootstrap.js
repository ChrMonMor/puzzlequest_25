import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Load stored token (if any) and set default Authorization header for axios
const storedToken = localStorage.getItem('jwt') || localStorage.getItem('token') || null;
if (storedToken) {
	window.axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
}

// Interceptor: capture refreshed Authorization header from responses and persist it.
window.axios.interceptors.response.use(
	(response) => {
		try {
			const auth = response.headers['authorization'] || response.headers['Authorization'];
			if (auth) {
				const raw = auth.replace(/^Bearer\s+/i, '');
				// Persist under both keys used by the app
				localStorage.setItem('jwt', raw);
				localStorage.setItem('token', raw);
				// Update axios defaults for subsequent requests
				window.axios.defaults.headers.common['Authorization'] = `Bearer ${raw}`;
			}
		} catch (e) {
			// swallow any header parsing errors
			console.warn('Failed to parse Authorization header from response', e);
		}
		return response;
	},
	(error) => {
		// also check error responses (e.g., 401 with refreshed token header)
		if (error && error.response) {
			try {
				const auth = error.response.headers['authorization'] || error.response.headers['Authorization'];
				if (auth) {
					const raw = auth.replace(/^Bearer\s+/i, '');
					localStorage.setItem('jwt', raw);
					localStorage.setItem('token', raw);
					window.axios.defaults.headers.common['Authorization'] = `Bearer ${raw}`;
				}
			} catch (e) { /* noop */ }
		}
		return Promise.reject(error);
	}
);
