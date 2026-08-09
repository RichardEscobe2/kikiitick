import axios from 'axios';

window.axios = axios;

// 🔒 Configuración de credenciales y URL para Laravel Sanctum
axios.defaults.withCredentials = true;
// 🛡️ Si VITE_API_URL no está definido, usar el origin actual de la página (window.location.origin)
// en vez de un host hardcodeado: evita que 'localhost:8000' vs '127.0.0.1:8000' (mismo servidor,
// distinto origin para el navegador) se traten como cross-origin y disparen bloqueos de CORS.
axios.defaults.baseURL = import.meta.env.VITE_API_URL ?? window.location.origin;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';