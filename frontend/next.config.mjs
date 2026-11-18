const backend = process.env.PORTAL_API_URL || 'http://127.0.0.1:8000';
export default {
  async rewrites() {
    return ['categories', 'municipalities', 'reports', 'tracking', 'tracking/messages'].map(path => ({
      source: '/api/' + path,
      destination: backend + '/api/' + path,
    }));
  },
};
