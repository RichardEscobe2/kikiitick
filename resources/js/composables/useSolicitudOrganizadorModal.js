import { ref } from 'vue';

// Estado global (singleton), mismo patrón que useAuthModal.js: un ref a nivel de
// módulo compartido por cualquier componente que llame a useSolicitudOrganizadorModal().
const isOpen = ref(false);

export function useSolicitudOrganizadorModal() {
    const abrirSolicitudModal = () => {
        isOpen.value = true;
    };

    const cerrarSolicitudModal = () => {
        isOpen.value = false;
    };

    return {
        isOpen,
        abrirSolicitudModal,
        cerrarSolicitudModal,
    };
}
