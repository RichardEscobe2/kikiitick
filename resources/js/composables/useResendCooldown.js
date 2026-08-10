import { ref, onUnmounted } from 'vue';

/**
 * Temporizador de reenvío reutilizado por VerificationCard.vue y
 * ResetPasswordView.vue — mismo comportamiento exacto en ambos flujos
 * (verificación de cuenta y recuperación de contraseña), extraído aquí en
 * vez de duplicar el setInterval/cleanup en los dos componentes.
 */
export function useResendCooldown(segundosIniciales = 60) {
  const segundosRestantes = ref(segundosIniciales);
  let intervalId = null;

  const detener = () => {
    if (intervalId) {
      clearInterval(intervalId);
      intervalId = null;
    }
  };

  const iniciar = () => {
    segundosRestantes.value = segundosIniciales;
    detener();
    intervalId = setInterval(() => {
      if (segundosRestantes.value > 0) {
        segundosRestantes.value -= 1;
      } else {
        detener();
      }
    }, 1000);
  };

  onUnmounted(detener);

  return { segundosRestantes, iniciar };
}
