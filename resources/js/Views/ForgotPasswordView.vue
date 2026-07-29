<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
    <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md">
      <!-- Icono superior decorativo -->
      <div class="flex justify-center mb-4">
        <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 0121 9z" />
          </svg>
        </div>
      </div>

      <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">¿Olvidaste tu contraseña?</h2>
      <p class="text-sm text-gray-500 text-center mb-6">
        Ingresa tu correo electrónico y te enviaremos un código de 6 dígitos para restablecerla.
      </p>

      <form @submit.prevent="handleForgotPassword" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Correo Electrónico</label>
          <input
            v-model="correo"
            type="email"
            placeholder="ejemplo@correo.com"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
            :disabled="loading"
            required
          />
        </div>

        <p v-if="error" class="text-red-500 text-sm text-center bg-red-50 p-2 rounded-lg border border-red-200">
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="loading || !correo"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-lg transition duration-200 disabled:opacity-50 shadow-md"
        >
          {{ loading ? 'Enviando código...' : 'Enviar Código de Recuperación' }}
        </button>
      </form>

      <div class="mt-6 text-center">
        <router-link to="/login" class="text-sm text-indigo-600 hover:underline font-medium">
          ← Volver al Inicio de Sesión
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const correo = ref('');
const loading = ref(false);
const error = ref('');

const handleForgotPassword = async () => {
  loading.value = true;
  error.value = '';

  try {
    // 1. Obtener cookie CSRF de Sanctum
    await axios.get('/sanctum/csrf-cookie');
    
    // 2. Enviar petición al backend
    await axios.post('/api/forgot-password', { 
      correo: correo.value 
    });

    // 3. Redirigir a la vista de Restablecer Contraseña pasando el correo como query param
    router.push({ 
      name: 'reset-password', 
      query: { email: correo.value } 
    });
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo enviar el código. Revisa que el correo esté registrado.';
  } finally {
    loading.value = false;
  }
};
</script>