// Funciones de saneo/filtrado reutilizables para campos de contacto — mismo
// patrón de doble capa ya probado en SolicitudOrganizadorModal.vue:
// @keydown bloquea la tecla ANTES de que el carácter se renderice; las
// funciones sanear*() son el respaldo para @input (cubre pegar texto, que no
// dispara keydown). Los patrones deben coincidir exactamente con las reglas
// del backend (UpdatePerfilRequest, AuthController::solicitudOrganizador).

const esAtajoTeclado = (e) => e.ctrlKey || e.metaKey || e.altKey;
const TECLAS_NAVEGACION = ['Backspace', 'Tab', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];

export function onKeydownTelefono(e) {
  if (esAtajoTeclado(e)) return;
  if (['Backspace', 'Tab', 'Delete', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
  if (!/^[0-9]$/.test(e.key)) e.preventDefault();
}

export function onKeydownRfc(e) {
  if (esAtajoTeclado(e) || TECLAS_NAVEGACION.includes(e.key)) return;
  if (e.key.length === 1 && !/^[a-zA-Z0-9]$/.test(e.key)) e.preventDefault();
}

export function onKeydownNombre(e) {
  if (esAtajoTeclado(e) || TECLAS_NAVEGACION.includes(e.key)) return;
  if (e.key.length === 1 && !/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]$/.test(e.key)) e.preventDefault();
}

export const sanearTelefono = (valor) => (valor || '').replace(/[^0-9]/g, '').slice(0, 10);
export const sanearRfc = (valor) => (valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 13);
export const sanearNombre = (valor) => (valor || '').replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');

export const PATRON_TELEFONO = /^[0-9]{10}$/;
export const PATRON_RFC = /^[A-Z0-9]{12,13}$/;
export const PATRON_NOMBRE = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
