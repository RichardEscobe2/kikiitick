<template>
  <div class="min-h-[75vh] flex items-center justify-center py-10 px-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8 text-center">
      
      <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
        📩
      </div>

      <h2 class="text-2xl font-bold text-gray-900">Verifica tu correo</h2>
      <p class="text-xs text-gray-500 mt-2">
        Hemos enviado un código de 6 dígitos a:<br>
        <strong class="text-gray-800">{{ correo }}</strong>
      </p>

      <div v-if="errorMsg" class="my-3 p-2 bg-red-50 text-red-600 text-xs rounded-lg">
        {{ errorMsg }}
      </div>

      <form @submit.prevent="verificarCodigo" class="mt-6 space-y-4">
        <div>
          <input 
            v-model="codigo"
            type="text" 
            maxlength="6"
            placeholder="000000"
            required
            class="w-full py-3 text-center text-2xl tracking-[0.5em] font-mono font-bold bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none"
          />
        </div>

        <button 
          type="submit" 
          :disabled="codigo.length !== 6 || cargando"
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition-all"
        >
          {{ cargando ? 'Verificando...' : 'Confirmar Código' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { axios } from '../composables/useAuth';

const route = useRoute();
const router = useRouter();

const correo = ref('');
const codigo = ref('');
const cargando = ref(false);
const errorMsg = ref('');

onMounted(() => {
  correo.value = route.query.correo || '';
});

const verificarCodigo = async () => {
  cargando.value = true;
  errorMsg.value = '';

  try {
    await axios.post('/api/verificar-codigo', { correo: correo.value, codigo: codigo.value });
    alert('¡Cuenta activada con éxito! Ahora puedes iniciar sesión.');
    router.push('/login');
  } catch (e) {
    errorMsg.value = e.response?.data?.error || 'Código inválido.';
  } finally {
    cargando.value = false;
  }
};
</script>