import { createApp } from 'vue';
import './bootstrap';
import App from './App.vue'; // 👈 Importamos App.vue en vez de MainLayout
import router from './router';
import '../css/app.css';
// Creamos un componente raíz mínimo que renderice la ruta activa
createApp(App)
    .use(router)
    .mount('#app');