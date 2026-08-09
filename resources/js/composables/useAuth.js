import { ref, computed } from 'vue';
import axios from 'axios';
import router from '../router';

export { axios };

const user = ref(null);
const loading = ref(true);

export function useAuth() {
    const isAuthenticated = computed(() => !!user.value);
    const userRole = computed(() => user.value?.rol || user.value?.role || 'cliente');
    const isAdmin = computed(() => userRole.value === 'admin');
    const isOrganizador = computed(() => userRole.value === 'organizador');
    const isVendedor = computed(() => userRole.value === 'vendedor');

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
            // El logout del cliente debe completarse aunque la petición al servidor falle.
        } finally {
            // Limpiamos el estado en el cliente
            user.value = null;
            loading.value = false;

            // 🛡️ Elimina cualquier dato de sesión persistido en localStorage para no
            // dejar nombre/correo/rol del usuario expuesto tras cerrar sesión.
            localStorage.removeItem('usuario_kikiitick');

            // Redirigimos a Login y recargamos para limpiar estados en memoria
            window.location.href = '/login';
        }
    };

    return {
        user,
        loading,
        isAuthenticated,
        userRole,
        isAdmin,
        isOrganizador,
        isVendedor,
        fetchUser,
        logout
    };
}