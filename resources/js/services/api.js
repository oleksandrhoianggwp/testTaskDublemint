import axios from 'axios';

const tokenKey = 'dublemint_auth_token';

export const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = getAuthToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && getAuthToken()) {
      clearAuthToken();
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    }

    return Promise.reject(error);
  },
);

export function getAuthToken() {
  return window.localStorage.getItem(tokenKey);
}

export function setAuthToken(token) {
  window.localStorage.setItem(tokenKey, token);
}

export function clearAuthToken() {
  window.localStorage.removeItem(tokenKey);
}

export function getApiError(error, fallback = 'The request could not be completed.') {
  if (!error.response) {
    return 'The server is unreachable. Check your connection and try again.';
  }

  const validationErrors = error.response.data?.errors;
  if (validationErrors) {
    const firstError = Object.values(validationErrors).flat()[0];
    if (firstError) return firstError;
  }

  return error.response.data?.message || fallback;
}
