<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
    <div class="bg-white p-8 rounded-3xl shadow-xl w-full max-w-md border border-gray-100">
      
      <!-- Icono superior decorativo -->
      <div class="flex justify-center mb-4">
        <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
      </div>

      <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-1">Restablecer Contraseña</h2>
      <p class="text-sm text-gray-500 text-center mb-6 leading-relaxed">
        Ingresa el código enviado a <span class="font-bold text-gray-800">{{ correo }}</span> y escribe tu nueva contraseña.
      </p>

      <form @submit.prevent="handleResetPassword" class="space-y-4">
        <!-- Código de 6 dígitos -->
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-2 text-center tracking-wider">Código de 6 dígitos</label>
          <input
            v-model="codigo"
            type="text"
            maxlength="6"
            placeholder="0 0 0 0 0 0"
            class="w-full text-center text-2xl font-mono tracking-[0.4em] py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all uppercase"
            :disabled="loading || !!success"
            required
          />
        </div>

        <!-- Nueva Contraseña con Ojito -->
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1 tracking-wider">Nueva Contraseña</label>
          <div class="relative">
            <input
              v-model="contrasena"
              :type="mostrarContrasena ? 'text' : 'password'"
              placeholder="••••••••"
              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all pr-10"
              :disabled="loading || !!success"
              required
            />
            <button
              type="button"
              @click="mostrarContrasena = !mostrarContrasena"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-indigo-600 focus:outline-none"
              tabindex="-1"
            >
              <!-- Icono Ojo Abierto -->
              <svg v-if="!mostrarContrasena" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <!-- Icono Ojo Cerrado -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.048 10.048 0 014.122-.963c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Confirmar Contraseña con Ojito -->
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1 tracking-wider">Confirmar Contraseña</label>
          <div class="relative">
            <input
              v-model="contrasenaConfirmation"
              :type="mostrarConfirmacion ? 'text' : 'password'"
              placeholder="••••••••"
              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all pr-10"
              :disabled="loading || !!success"
              required
            />
            <button
              type="button"
              @click="mostrarConfirmacion = !mostrarConfirmacion"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-indigo-600 focus:outline-none"
              tabindex="-1"
            >
              <svg v-if="!mostrarConfirmacion" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.048 10.048 0 014.122-.963c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Validaciones en tiempo real (Live Requirements) -->
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-xs space-y-1.5 mt-2">
          <p class="font-bold text-gray-700 mb-1">Requisitos de la contraseña:</p>
          <div class="flex items-center gap-2" :class="reqMinLength ? 'text-green-600 font-medium' : 'text-gray-400'">
            <span>{{ reqMinLength ? '✓' : '○' }}</span>
            <span>Mínimo 8 caracteres</span>
          </div>
          <div class="flex items-center gap-2" :class="reqUppercase ? 'text-green-600 font-medium' : 'text-gray-400'">
            <span>{{ reqUppercase ? '✓' : '○' }}</span>
            <span>Al menos una letra mayúscula</span>
          </div>
          <div class="flex items-center gap-2" :class="reqLowercase ? 'text-green-600 font-medium' : 'text-gray-400'">
            <span>{{ reqLowercase ? '✓' : '○' }}</span>
            <span>Al menos una letra minúscula</span>
          </div>
          <div class="flex items-center gap-2" :class="reqNumber ? 'text-green-600 font-medium' : 'text-gray-400'">
            <span>{{ reqNumber ? '✓' : '○' }}</span>
            <span>Al menos un número</span>
          </div>
          <div class="flex items-center gap-2" :class="reqMatch ? 'text-green-600 font-medium' : 'text-gray-400'">
            <span>{{ reqMatch ? '✓' : '○' }}</span>
            <span>Las contraseñas coinciden</span>
          </div>
        </div>

        <!-- Mensajes de Estado -->
        <div v-if="error" class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded-r-xl">
          {{ error }}
        </div>

        <div v-if="success" class="p-4 bg-green-50 border border-green-200 text-green-800 text-xs rounded-xl text-center space-y-2">
          <p class="font-bold text-sm">🎉 {{ success }}</p>
          <p class="text-green-600">Redirigiendo al inicio de sesión en unos segundos...</p>
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
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
        >
          {{ loading ? 'Actualizando contraseña...' : 'Cambiar Contraseña' }}
        </button>
      </form>

      <!-- Reenviar código -->
      <div v-if="!success" class="mt-6 text-center border-t border-gray-100 pt-4">
        <button 
          @click="reenviarCodigo" 
          :disabled="loading" 
          type="button"
          class="text-xs text-indigo-600 hover:underline font-bold disabled:opacity-50"
        >
          ¿No recibiste el código? Reenviar código
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();

const correo = ref(route.query.email || '');
const codigo = ref('');
const contrasena = ref('');
const contrasenaConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

// Control de visibilidad para los ojos
const mostrarContrasena = ref(false);
const mostrarConfirmacion = ref(false);

// Evaluaciones reactivas en vivo para la contraseña
const reqMinLength = computed(() => contrasena.value.length >= 8);
const reqUppercase = computed(() => /[A-Z]/.test(contrasena.value));
const reqLowercase = computed(() => /[a-z]/.test(contrasena.value));
const reqNumber = computed(() => /[0-9]/.test(contrasena.value));
const reqMatch = computed(() => contrasena.value.length > 0 && contrasena.value === contrasenaConfirmation.value);

// Formulario válido si cumple todo + código de 6 caracteres
const isFormValid = computed(() => {
  return (
    codigo.value.length === 6 &&
    reqMinLength.value &&
    reqUppercase.value &&
    reqLowercase.value &&
    reqNumber.value &&
    reqMatch.value
  );
});

const handleResetPassword = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    await axios.get('/sanctum/csrf-cookie');
    
    const response = await axios.post('/api/reset-password', {
      correo: correo.value,
      codigo: codigo.value,
      contrasena: contrasena.value,
      contrasena_confirmation: contrasenaConfirmation.value
    });

    success.value = response.data.message;
    
    // Redirección automática al Login tras 2.5 segundos
    setTimeout(() => {
      irAlLogin();
    }, 2500);

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
  error.value = '';
  try {
    await axios.post('/api/forgot-password', { correo: correo.value });
    alert('Nuevo código enviado a tu correo.');
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo reenviar el código.';
  }
};
</script>