<template>
  <div class="min-h-[75vh] flex items-center justify-center py-10 px-4">
    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-100/80 shadow-xl shadow-slate-200/60 p-8">

      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/80 ring-1 ring-indigo-100 flex items-center justify-center mx-auto mb-5">
        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>

      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight text-center">Restablecer contraseña</h1>
      <p class="text-sm text-slate-500 text-center mt-2 leading-relaxed">
        Ingresa el código enviado a
        <span class="font-semibold text-slate-900">{{ correo }}</span>
        y escribe tu nueva contraseña.
      </p>

      <form @submit.prevent="handleResetPassword" class="mt-7 space-y-5">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 text-center">Código de 6 dígitos</label>
          <OTPCodeInput v-model="codigo" :disabled="loading || !!success" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Nueva contraseña</label>
          <div class="relative">
            <input
              v-model="contrasena"
              :type="mostrarContrasena ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="••••••••"
              class="w-full px-4 py-3 pr-10 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 focus:outline-none transition-all"
              :disabled="loading || !!success"
              required
            />
            <button
              type="button"
              @click="mostrarContrasena = !mostrarContrasena"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors cursor-pointer"
              tabindex="-1"
            >
              <svg v-if="!mostrarContrasena" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.048 10.048 0 014.122-.963c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
              </svg>
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Confirmar contraseña</label>
          <div class="relative">
            <input
              v-model="contrasenaConfirmation"
              :type="mostrarConfirmacion ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="••••••••"
              class="w-full px-4 py-3 pr-10 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 focus:outline-none transition-all"
              :disabled="loading || !!success"
              required
            />
            <button
              type="button"
              @click="mostrarConfirmacion = !mostrarConfirmacion"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors cursor-pointer"
              tabindex="-1"
            >
              <svg v-if="!mostrarConfirmacion" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.048 10.048 0 014.122-.963c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Checklist de requisitos con íconos SVG y transición de color -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2">
          <p class="text-xs font-bold text-slate-700 mb-1">Requisitos de la contraseña</p>
          <div
            v-for="req in requisitos"
            :key="req.label"
            class="flex items-center gap-2 text-xs transition-colors duration-200"
            :class="req.cumplido ? 'text-emerald-600 font-medium' : 'text-slate-400'"
          >
            <svg v-if="req.cumplido" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>{{ req.label }}</span>
          </div>
        </div>

        <div v-if="error" class="p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-700 text-xs rounded-r-xl">
          {{ error }}
        </div>

        <div v-if="success" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl text-center space-y-2">
          <p class="font-bold text-sm">🎉 {{ success }}</p>
          <p class="text-emerald-600">Redirigiendo al inicio de sesión en unos segundos...</p>
          <button
            type="button"
            @click="irAlLogin"
            class="inline-block mt-1 font-bold text-indigo-600 hover:underline cursor-pointer"
          >
            Ir al inicio de sesión ahora →
          </button>
        </div>

        <button
          v-if="!success"
          type="submit"
          :disabled="loading || !isFormValid"
          class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none transition-all cursor-pointer"
        >
          {{ loading ? 'Actualizando contraseña...' : 'Actualizar Contraseña' }}
        </button>
      </form>

      <div v-if="!success" class="mt-6 text-center border-t border-slate-100 pt-4">
        <button
          @click="reenviarCodigo"
          :disabled="loading || segundosRestantes > 0"
          type="button"
          class="text-xs font-bold transition-colors"
          :class="segundosRestantes > 0 ? 'text-slate-300 cursor-not-allowed' : 'text-indigo-600 hover:text-indigo-800 cursor-pointer'"
        >
          {{ segundosRestantes > 0 ? `Reenviar código en ${segundosRestantes}s` : '¿No recibiste el código? Reenviar' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { axios } from '../composables/useAuth';
import OTPCodeInput from '../Components/OTPCodeInput.vue';
import { useResendCooldown } from '../composables/useResendCooldown';

const route = useRoute();
const router = useRouter();

let redirectTimer = null;

const correo = ref(route.query.email || '');
const codigo = ref('');
const contrasena = ref('');
const contrasenaConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

const mostrarContrasena = ref(false);
const mostrarConfirmacion = ref(false);

const { segundosRestantes, iniciar } = useResendCooldown(60);
iniciar();

const reqMinLength = computed(() => contrasena.value.length >= 8);
const reqUppercase = computed(() => /[A-Z]/.test(contrasena.value));
const reqLowercase = computed(() => /[a-z]/.test(contrasena.value));
const reqNumber = computed(() => /[0-9]/.test(contrasena.value));
const reqMatch = computed(() => contrasena.value.length > 0 && contrasena.value === contrasenaConfirmation.value);

const requisitos = computed(() => [
  { label: 'Mínimo 8 caracteres', cumplido: reqMinLength.value },
  { label: 'Al menos una letra mayúscula', cumplido: reqUppercase.value },
  { label: 'Al menos una letra minúscula', cumplido: reqLowercase.value },
  { label: 'Al menos un número', cumplido: reqNumber.value },
  { label: 'Las contraseñas coinciden', cumplido: reqMatch.value },
]);

const isFormValid = computed(() => (
  codigo.value.length === 6 &&
  reqMinLength.value &&
  reqUppercase.value &&
  reqLowercase.value &&
  reqNumber.value &&
  reqMatch.value
));

const handleResetPassword = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await axios.post('/api/reset-password', {
      correo: correo.value,
      codigo: codigo.value,
      contrasena: contrasena.value,
      contrasena_confirmation: contrasenaConfirmation.value,
    });

    success.value = response.data.message;
    redirectTimer = setTimeout(irAlLogin, 2500);
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al actualizar la contraseña. Revisa el código e intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

const irAlLogin = () => {
  router.push({ name: 'Login' });
};

const reenviarCodigo = async () => {
  if (segundosRestantes.value > 0) return;
  error.value = '';
  try {
    await axios.post('/api/forgot-password', { correo: correo.value });
    iniciar();
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo reenviar el código.';
  }
};

onUnmounted(() => {
  if (redirectTimer) clearTimeout(redirectTimer);
});
</script>
