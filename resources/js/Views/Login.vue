<template>
  <div class="min-h-[80vh] flex items-center justify-center py-10 px-4">
    <!-- Tarjeta blanca con sombra elevada y borde nítido -->
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl shadow-slate-300/50 border border-slate-200/80 p-8 transition-all">
      
      <!-- Header -->
      <div class="text-center mb-6">
        <h1 class="text-3xl font-extrabold text-indigo-600 tracking-tight">KikiiTick</h1>
        <h2 class="text-xl font-bold text-gray-900 mt-2">Iniciar Sesión</h2>
        <p class="text-sm text-gray-500 mt-1">Ingresa para acceder a tus boletos</p>
      </div>

      <div v-if="errorMsg" class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl">
        {{ errorMsg }}
      </div>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Correo electrónico</label>
          <input 
            v-model="form.correo"
            type="email" 
            required
            placeholder="correo@ejemplo.com"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
          />
        </div>

        <div>
          <div class="flex justify-between items-center mb-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">Contraseña</label>
            <router-link to="/forgot-password" class="text-xs text-indigo-600 hover:underline">¿Olvidaste tu contraseña?</router-link>
          </div>
          <input 
            v-model="form.contrasena"
            type="password" 
            required
            placeholder="••••••••"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
          />
        </div>

        <!-- Leyenda Legal -->
        <p class="text-[11px] text-gray-500 leading-relaxed pt-2">
          Al iniciar sesión en tu cuenta de KikiiTick, aceptas nuestros 
          <a href="#" class="text-indigo-600 hover:underline">Términos de servicio</a> y nuestra 
          <a href="#" class="text-indigo-600 hover:underline">Declaración de privacidad</a>.
        </p>

        <button 
          type="submit" 
          :disabled="cargando"
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition-all"
        >
          {{ cargando ? 'Iniciando sesión...' : 'Iniciar sesión' }}
        </button>
      </form>

      <p class="text-center text-xs text-gray-600 mt-6">
        ¿Aún no tienes cuenta? 
        <router-link to="/registro" class="text-indigo-600 font-bold hover:underline">Regístrate aquí</router-link>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { fetchUser } = useAuth();
const cargando = ref(false);
const errorMsg = ref('');

const form = ref({
  correo: '',
  contrasena: ''
});

const handleLogin = async () => {
  cargando.value = true;
  errorMsg.value = '';

  try {
    const res = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    });

    const data = await res.json();

    if (res.ok) {
      localStorage.setItem('usuario_kikiitick', JSON.stringify(data.user));

      if (typeof fetchUser === 'function') {
        await fetchUser();
      }

      const rol = data.user?.rol;
      if (rol === 'admin') {
        router.push({ name: 'AdminUsuarios' });
      } else if (rol === 'organizador') {
        router.push({ name: 'Organizador' });
      } else {
        router.push({ name: 'Home' });
      }

    } else {
      if (data.requiere_verificacion) {
        router.push({ name: 'VerificarCodigo', query: { correo: data.correo } });
      } else {
        errorMsg.value = data.error || 'Credenciales inválidas.';
      }
    }
  } catch (e) {
    errorMsg.value = 'Ocurrió un error de conexión.';
  } finally {
    cargando.value = false;
  }
};
</script>