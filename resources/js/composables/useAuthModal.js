import { ref } from 'vue';

// Estado global (singleton), mismo patrón que useAuth.js: refs a nivel de módulo
// compartidas por todos los componentes que llamen a useAuthModal().
const isOpen = ref(false);
const activeTab = ref('login');
const onSuccessCallback = ref(null);

export function useAuthModal() {
    /**
     * Abre el modal de autenticación rápida.
     * @param {Function|null} callback - Se ejecuta automáticamente tras un login/registro exitoso.
     * @param {'login'|'register'} tab - Pestaña inicial a mostrar.
     */
    const openAuthModal = (callback = null, tab = 'login') => {
        onSuccessCallback.value = callback;
        activeTab.value = tab;
        isOpen.value = true;
    };

    const closeAuthModal = () => {
        isOpen.value = false;
        onSuccessCallback.value = null;
    };

    /**
     * Se invoca desde QuickAuthModal tras un login/registro/verificación exitosos:
     * cierra el modal y ejecuta el callback pendiente (ej. reintentar la reserva de asientos).
     */
    const handleSuccess = () => {
        const callback = onSuccessCallback.value;
        isOpen.value = false;
        onSuccessCallback.value = null;

        if (typeof callback === 'function') {
            callback();
        }
    };

    return {
        isOpen,
        activeTab,
        openAuthModal,
        closeAuthModal,
        handleSuccess,
    };
}
