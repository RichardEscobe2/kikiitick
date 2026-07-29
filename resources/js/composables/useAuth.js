import { ref, computed } from 'vue';
import axios from 'axios';
import router from '../router';

export { axios };

const user = ref(null);
const loading = ref(true);

export function useAuth() {
    const isAuthenticated = computed(() => !!user.value);
    const userRole = computed(() => user.value?.rol || user.value?.role || 'cliente');

    // 1. Obtener usuario actual
    const fetchUser = async () => {
        try {
            const response = await axios.get('/api/user');
            user.value = response.data;
        } catch (error) {
            // Es normal recibir 401 si no hay sesión activa
            user.value = null;
        } finally {
            loading.value = false;
        }
    };

    // 2. Cerrar sesión limpiamente
    const logout = async () => {
        try {
            // ✅ RUTA CORREGIDA: /api/logout
            await axios.post('/api/logout');
        } catch (error) {
            console.error('Error al cerrar sesión en el servidor:', error);
        } finally {
            // Limpiamos el estado en el cliente
            user.value = null;
            loading.value = false;
            
            // Redirigimos a Login y recargamos para limpiar estados en memoria
            window.location.href = '/login';
        }
    };

    return {
        user,
        loading,
        isAuthenticated,
        userRole,
        fetchUser,
        logout
    };
}