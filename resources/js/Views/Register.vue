<template>
  <div class="min-h-[80vh] flex items-center justify-center py-10 px-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
      
      <!-- Logo y Título -->
      <div class="text-center mb-6">
        <h1 class="text-3xl font-extrabold text-indigo-600 tracking-tight">KikiiTick</h1>
        <h2 class="text-xl font-bold text-gray-900 mt-2">Crear una cuenta</h2>
        <p class="text-sm text-gray-500 mt-1">Compra tus boletos de forma rápida y segura</p>
      </div>

      <!-- Alertas de Error Estéticas -->
      <div 
        v-if="errorMsg" 
        class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl flex items-start gap-3 shadow-sm transition-all"
      >
        <span class="text-base">⚠️</span>
        <div class="flex-1 font-medium">{{ errorMsg }}</div>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Nombre Completo -->
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombre completo</label>
          <input 
            v-model="form.nombre"
            type="text" 
            required
            placeholder="Ej. Ricardo Escobedo"
            @input="filtrarNombre"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
          />
          <!-- FEEDBACK DE VALIDACIÓN DE NOMBRE -->
          <span v-if="errorNombre" class="text-xs text-red-500 mt-1 block font-medium">
            {{ errorNombre }}
          </span>
        </div>

        <!-- Correo Electrónico -->
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

        <!-- Contraseña -->
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contraseña</label>
          <input 
            v-model="form.contrasena"
            type="password" 
            required
            placeholder="••••••••"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
          />

          <!-- LISTA DE REQUISITOS EN VIVO -->
          <div class="mt-3 p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-1.5">
            <p class="text-xs font-semibold text-gray-500 mb-2">La contraseña debe contener:</p>
            
            <div class="flex items-center text-xs transition-colors duration-200"
                 :class="validaciones.longitud ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              <span class="mr-2 text-sm">{{ validaciones.longitud ? '✅' : '⚪' }}</span>
              Mínimo 8 caracteres
            </div>

            <div class="flex items-center text-xs transition-colors duration-200"
                 :class="validaciones.mayuscula ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              <span class="mr-2 text-sm">{{ validaciones.mayuscula ? '✅' : '⚪' }}</span>
              Al menos una letra mayúscula (A-Z)
            </div>

            <div class="flex items-center text-xs transition-colors duration-200"
                 :class="validaciones.numero ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              <span class="mr-2 text-sm">{{ validaciones.numero ? '✅' : '⚪' }}</span>
              Al menos un número (0-9)
            </div>

            <div class="flex items-center text-xs transition-colors duration-200"
                 :class="validaciones.especial ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              <span class="mr-2 text-sm">{{ validaciones.especial ? '✅' : '⚪' }}</span>
              Un carácter especial (ej. !@#$%^&*)
            </div>
          </div>
        </div>

        <!-- CONFIRMAR CONTRASEÑA -->
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirmar contraseña</label>
          <input 
            v-model="form.contrasena_confirmation"
            type="password" 
            required
            placeholder="••••••••"
            :class="[
              'w-full px-4 py-3 border rounded-xl text-sm focus:outline-none transition-all',
              form.contrasena_confirmation 
                ? (coincidenContrasenas 
                    ? 'bg-emerald-50/30 border-emerald-500 focus:ring-2 focus:ring-emerald-500' 
                    : 'bg-red-50/30 border-red-500 focus:ring-2 focus:ring-red-500')
                : 'bg-gray-50 border-gray-200 focus:bg-white focus:ring-2 focus:ring-indigo-600'
            ]"
          />

          <!-- FEEDBACK ESTÉTICO DE COINCIDENCIA EN TIEMPO REAL -->
          <div v-if="form.contrasena_confirmation" class="mt-2 text-xs font-medium">
            <span v-if="coincidenContrasenas" class="text-emerald-600 flex items-center gap-1.5">
              <span>✅</span> Las contraseñas coinciden
            </span>
            <span v-else class="text-red-500 flex items-center gap-1.5">
              <span>❌</span> Las contraseñas no coinciden
            </span>
          </div>
        </div>

        <!-- Términos y Condiciones -->
        <p class="text-[11px] text-gray-500 leading-relaxed pt-2">
          Al continuar y hacer clic en <strong>"Crear cuenta"</strong>, aceptas los 
          <a href="#" class="text-indigo-600 hover:underline">Términos de uso de KikiiTick</a> y confirmas que has leído nuestra 
          <a href="#" class="text-indigo-600 hover:underline">Política de privacidad</a>.
        </p>

        <!-- Botón de Registro -->
        <button 
          type="submit" 
          :disabled="!todoValido || cargando"
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
        >
          <span v-if="cargando">Procesando...</span>
          <span v-else>Crear cuenta</span>
        </button>
      </form>

      <!-- Enlace a Login -->
      <p class="text-center text-xs text-gray-600 mt-6">
        ¿Ya tienes una cuenta? 
        <router-link to="/login" class="text-indigo-600 font-bold hover:underline">Iniciar sesión</router-link>
      </p>

      <!-- ======================================================== -->
      <!-- BANNER / OPCIÓN PARA REGISTRARSE COMO ORGANIZADOR        -->
      <!-- ======================================================== -->
      <div class="mt-6 pt-6 border-t border-gray-100 text-center">
        <p class="text-xs text-gray-500 mb-2 font-medium">
          ¿Eres dueño de un recinto o buscas vender boletos?
        </p>
        <router-link 
          to="/registro-organizador" 
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-xl text-xs font-bold transition-all border border-indigo-100 w-full"
        >
          Registrarse como Organizador
        </router-link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const cargando = ref(false);
const errorMsg = ref('');
const errorNombre = ref('');

const form = ref({
  nombre: '',
  correo: '',
  contrasena: '',
  contrasena_confirmation: ''
});

// Filtra en tiempo real para limpiar números y símbolos
const filtrarNombre = (e) => {
  const valorOriginal = e.target.value;
  // Permite solo letras (con acentos y ñ) y espacios
  const valorLimpio = valorOriginal.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');

  if (valorOriginal !== valorLimpio) {
    errorNombre.value = 'El nombre solo puede contener letras y espacios.';
  } else {
    errorNombre.value = '';
  }

  form.value.nombre = valorLimpio;
};

// Validación reactiva de la estructura del nombre
const nombreValido = computed(() => {
  const n = form.value.nombre.trim();
  return n.length > 0 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(n);
});

// Validación reactiva de requisitos de la contraseña
const validaciones = computed(() => {
  const p = form.value.contrasena;
  return {
    longitud: p.length >= 8,
    mayuscula: /[A-Z]/.test(p),
    numero: /[0-9]/.test(p),
    especial: /[!@#$%^&*(),.?":{}|<>]/.test(p)
  };
});

// Validación de coincidencia de contraseñas
const coincidenContrasenas = computed(() => {
  return form.value.contrasena_confirmation !== '' && 
         form.value.contrasena === form.value.contrasena_confirmation;
});

// Habilita el botón solo si todo cumple strictly
const todoValido = computed(() => {
  return nombreValido.value &&
         form.value.correo.trim() !== '' &&
         validaciones.value.longitud &&
         validaciones.value.mayuscula &&
         validaciones.value.numero &&
         validaciones.value.especial &&
         coincidenContrasenas.value;
});

const handleSubmit = async () => {
  if (!todoValido.value) return;
  cargando.value = true;
  errorMsg.value = '';

  try {
    const res = await fetch('/api/registro', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    });

    const data = await res.json();

    if (res.ok) {
      router.push({ name: 'VerificarCodigo', query: { correo: form.value.correo } });
    } else {
      if (data.errors) {
        const primerosErrores = Object.values(data.errors).flat();
        errorMsg.value = primerosErrores[0] || 'Error de validación.';
      } else {
        errorMsg.value = data.error || data.message || 'Error al registrar la cuenta.';
      }
    }
  } catch (err) {
    errorMsg.value = 'Ocurrió un problema de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};
</script>