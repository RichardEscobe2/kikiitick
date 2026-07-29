import axios from 'axios';

window.axios = axios;

// 🔒 Configuración de credenciales y URL para Laravel Sanctum
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://localhost:8000';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';