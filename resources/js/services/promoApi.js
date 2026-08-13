import { api } from './api';

export const promoApi = {
  async claim(code) {
    const response = await api.post('/promo/claim', { code });
    return response.data;
  },

  async history({ status = '', page = 1, perPage = 8 } = {}) {
    const response = await api.get('/promo/history', {
      params: {
        status: status || undefined,
        page,
        per_page: perPage,
      },
    });
    return response.data;
  },
};
