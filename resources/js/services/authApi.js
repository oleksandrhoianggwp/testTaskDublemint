import { api } from './api';

export const authApi = {
  async login(credentials) {
    const response = await api.post('/login', credentials);
    return response.data;
  },

  async me() {
    const response = await api.get('/me');
    return response.data.data;
  },

  async logout() {
    await api.post('/logout');
  },
};
