const viteHost = (import.meta.env.VITE_HOST || '').trim().replace(/\/+$/, '');
const runtimeOrigin = typeof window !== 'undefined' ? window.location.origin : '';
const isLocalHost = /^(https?:\/\/)?(localhost|127\.0\.0\.1)(:\d+)?$/i.test(viteHost);

const ENV = {
    API_URL: viteHost && !isLocalHost ? viteHost : runtimeOrigin,
    DEMO: import.meta.env.VITE_DEMO,
    API_KEY: import.meta.env.VITE_API_KEY
};
export default ENV;
