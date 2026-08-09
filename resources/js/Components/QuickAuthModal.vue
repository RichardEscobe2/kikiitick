<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quick-auth-modal-title"
        @click.self="closeAuthModal"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl shadow-slate-300/50 border border-slate-200/80 p-8"
          >
            <button
              type="button"
              @click="closeAuthModal"
              aria-label="Cerrar"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 font-bold text-lg leading-none cursor-pointer"
            >
              ✕
            </button>

            <!-- Header -->
            <div class="text-center mb-6">
              <h1 id="quick-auth-modal-title" class="text-2xl font-extrabold text-indigo-600 tracking-tight">
                KikiiTick
              </h1>
              <p class="text-sm text-gray-500 mt-1">
                {{ step === 'verify' ? 'Verifica tu correo para continuar' : 'Inicia sesión o crea una cuenta para continuar' }}
              </p>
            </div>

            <!-- Tabs -->
            <div v-if="step !== 'verify'" class="grid grid-cols-2 gap-2 bg-gray-100 p-1 rounded-xl mb-6">
              <button
                type="button"
                @click="cambiarTab('login')"
                :class="[
                  'py-2 text-sm font-bold rounded-lg transition-all cursor-pointer',
                  step === 'login' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'
                ]"
              >
                Iniciar Sesión
              </button>
              <button
                type="button"
                @click="cambiarTab('register')"
                :class="[
                  'py-2 text-sm font-bold rounded-lg transition-all cursor-pointer',
                  step === 'register' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'
                ]"
              >
                Crear Cuenta
              </button>
            </div>

            <!-- Errores inline: nunca cierran el modal ni rompen el estado -->
            <div v-if="errorMsg" class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded-r-xl">
              {{ errorMsg }}
            </div>

            <!-- LOGIN -->
            <form v-if="step === 'login'" @submit.prevent="handleLogin" class="space-y-4">
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Correo electrónico</label>
                <input
                  v-model="loginForm.correo"
                  type="email"
                  required
                  placeholder="correo@ejemplo.com"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contraseña</label>
                <input
                  v-model="loginForm.contrasena"
                  type="password"
                  required
                  placeholder="••••••••"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <button
                type="submit"
                :disabled="cargando"
                class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition-all cursor-pointer"
              >
                {{ cargando ? 'Ingresando...' : 'Iniciar sesión' }}
              </button>
            </form>

            <!-- REGISTRO -->
            <form v-else-if="step === 'register'" @submit.prevent="handleRegister" class="space-y-4">
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombre completo</label>
                <input
                  v-model="registerForm.nombre"
                  type="text"
                  required
                  placeholder="Tu nombre"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Correo electrónico</label>
                <input
                  v-model="registerForm.correo"
                  type="email"
                  required
                  placeholder="correo@ejemplo.com"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contraseña</label>
                <input
                  v-model="registerForm.contrasena"
                  type="password"
                  required
                  minlength="8"
                  placeholder="Mínimo 8 caracteres"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <button
                type="submit"
                :disabled="cargando"
                class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition-all cursor-pointer"
              >
                {{ cargando ? 'Creando cuenta...' : 'Crear cuenta' }}
              </button>
            </form>

            <!-- VERIFICACIÓN DE CÓDIGO (registro no inicia sesión hasta verificar el correo) -->
            <form v-else @submit.prevent="handleVerify" class="space-y-4">
              <p class="text-xs text-gray-500 -mt-2 mb-2 text-center">
                Enviamos un código de 6 dígitos a <span class="font-bold text-gray-800">{{ correoAVerificar }}</span>
              </p>
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2 text-center tracking-wider">
                  Código de verificación
                </label>
                <input
                  v-model="codigoVerificacion"
                  type="text"
                  maxlength="6"
                  required
                  placeholder="0 0 0 0 0 0"
                  class="w-full text-center text-2xl font-mono tracking-[0.4em] py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>
              <button
                type="submit"
                :disabled="cargando"
                class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition-all cursor-pointer"
              >
                {{ cargando ? 'Verificando...' : 'Verificar y continuar' }}
              </button>
              <button
                type="button"
                @click="reenviarCodigo"
                :disabled="cargando"
                class="w-full text-xs text-indigo-600 hover:underline font-bold disabled:opacity-50 cursor-pointer"
              >
                ¿No recibiste el código? Reenviar
              </button>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useAuth, axios } from '../composables/useAuth';
import { useAuthModal } from '../composables/useAuthModal';

const { fetchUser } = useAuth();
const { isOpen, activeTab, closeAuthModal, handleSuccess } = useAuthModal();

// 'login' | 'register' | 'verify' — 'verify' es un paso interno de este componente,
// no forma parte del contrato tipado del composable (que solo expone 'login'|'register').
const step = ref('login');

const cargando = ref(false);
const errorMsg = ref('');

const loginForm = ref({ correo: '', contrasena: '' });
const registerForm = ref({ nombre: '', correo: '', contrasena: '' });
const codigoVerificacion = ref('');
const correoAVerificar = ref('');

const resetForms = () => {
  errorMsg.value = '';
  cargando.value = false;
  loginForm.value = { correo: '', contrasena: '' };
  registerForm.value = { nombre: '', correo: '', contrasena: '' };
  codigoVerificacion.value = '';
  correoAVerificar.value = '';
};

// Sincroniza el paso interno con la pestaña solicitada por openAuthModal(), y limpia
// el formulario cada vez que el modal se abre de nuevo.
watch(isOpen, (open) => {
  if (open) {
    step.value = activeTab.value;
    resetForms();
  }
});

const cambiarTab = (tab) => {
  step.value = tab;
  errorMsg.value = '';
};

const handleLogin = async () => {
  cargando.value = true;
  errorMsg.value = '';
  try {
    await axios.post('/api/login', loginForm.value);
    await fetchUser();
    handleSuccess();
  } catch (err) {
    const data = err.response?.data;
    if (err.response?.status === 403) {
      // Cuenta registrada pero correo no verificado: en vez de solo mostrar el error,
      // reenviamos el código automáticamente y pasamos a la verificación inline —
      // el usuario no tiene que volver a registrarse ni salir del modal.
      correoAVerificar.value = loginForm.value.correo;
      step.value = 'verify';
      const reenviado = await reenviarCodigo();
      if (reenviado) {
        errorMsg.value = 'Tu cuenta aún no ha sido verificada. Te reenviamos un código a tu correo.';
      }
    } else {
      errorMsg.value = data?.message || 'Credenciales inválidas.';
    }
  } finally {
    cargando.value = false;
  }
};

const handleRegister = async () => {
  cargando.value = true;
  errorMsg.value = '';
  try {
    // AuthController::register() siempre fuerza rol='cliente' en el servidor (RN-02);
    // no hace login automático, envía un código OTP por correo que hay que verificar.
    await axios.post('/api/registro', registerForm.value);
    correoAVerificar.value = registerForm.value.correo;
    step.value = 'verify';
  } catch (err) {
    const data = err.response?.data;
    const primerErrorValidacion = Object.values(data?.errors || {})[0]?.[0];
    errorMsg.value = data?.message || primerErrorValidacion || 'No se pudo completar el registro.';
  } finally {
    cargando.value = false;
  }
};

const handleVerify = async () => {
  cargando.value = true;
  errorMsg.value = '';
  try {
    await axios.post('/api/verificar-codigo', {
      correo: correoAVerificar.value,
      codigo: codigoVerificacion.value,
    });
    await fetchUser();
    handleSuccess();
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Código incorrecto o expirado.';
  } finally {
    cargando.value = false;
  }
};

const reenviarCodigo = async () => {
  errorMsg.value = '';
  try {
    await axios.post('/api/enviar-codigo', { correo: correoAVerificar.value });
    return true;
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'No se pudo reenviar el código.';
    return false;
  }
};

const onEsc = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeAuthModal();
  }
};

onMounted(() => window.addEventListener('keydown', onEsc));
onUnmounted(() => window.removeEventListener('keydown', onEsc));
</script>
