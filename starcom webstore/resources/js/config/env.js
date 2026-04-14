const runtimeAppUrl = typeof window !== 'undefined' && window.APP_URL
    ? String(window.APP_URL).trim().replace(/\/+$/, '')
    : '';
const viteHost = (import.meta.env.VITE_HOST || '').trim().replace(/\/+$/, '');
const runtimeOrigin = typeof window !== 'undefined' ? window.location.origin : '';
const isLocalHost = /^(https?:\/\/)?(localhost|127\.0\.0\.1)(:\d+)?$/i.test(viteHost);
const apiUrl = runtimeAppUrl || (viteHost && !isLocalHost ? viteHost : runtimeOrigin);
const apiKey = typeof window !== 'undefined' && typeof window.APP_KEY !== 'undefined'
    ? window.APP_KEY
    : '';
const demo = typeof window !== 'undefined' && typeof window.APP_DEMO !== 'undefined'
    ? window.APP_DEMO
    : import.meta.env.VITE_DEMO;

const ENV = {
    API_URL: apiUrl,
    DEMO: demo,
    API_KEY: apiKey
};
export default ENV;
