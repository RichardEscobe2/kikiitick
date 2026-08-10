<template>
  <div class="min-h-[75vh] flex items-center justify-center py-10 px-4">
    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-100/80 shadow-xl shadow-slate-200/60 p-8">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/80 ring-1 ring-indigo-100 flex items-center justify-center mx-auto mb-5">
        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 0121 9z" />
        </svg>
      </div>

      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight text-center">¿Olvidaste tu contraseña?</h1>
      <p class="text-sm text-slate-500 text-center mt-2 leading-relaxed max-w-xs mx-auto">
        Ingresa tu correo electrónico y te enviaremos un código de 6 dígitos para restablecerla.
      </p>

      <form @submit.prevent="handleForgotPassword" class="mt-7 space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Correo electrónico</label>
          <input
            v-model="correo"
            type="email"
            placeholder="ejemplo@correo.com"
            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 focus:outline-none transition-all"
            :disabled="loading"
            required
          />
        </div>

        <p v-if="error" class="text-rose-700 text-xs bg-rose-50 border-l-4 border-rose-500 rounded-r-xl p-3">
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="loading || !correo"
          class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none transition-all cursor-pointer"
        >
          {{ loading ? 'Enviando código...' : 'Enviar código de recuperación' }}
        </button>
      </form>

      <div class="mt-6 text-center border-t border-slate-100 pt-4">
        <router-link to="/login" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
          ← Volver al inicio de sesión
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { axios } from '../composables/useAuth';

const router = useRouter();
const correo = ref('');
const loading = ref(false);
const error = ref('');

const handleForgotPassword = async () => {
  loading.value = true;
  error.value = '';

  try {
    await axios.post('/api/forgot-password', { correo: correo.value });

    router.push({
      name: 'reset-password',
      query: { email: correo.value },
    });
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo enviar el código. Revisa que el correo esté registrado.';
  } finally {
    loading.value = false;
  }
};
</script>
