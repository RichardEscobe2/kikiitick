<template>
  <div class="max-w-2xl mx-auto my-10 px-4">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
      <div class="flex items-center gap-4 pb-6 border-b border-gray-100">
        <div class="w-14 h-14 rounded-2xl bg-indigo-100 text-indigo-700 font-black text-xl flex items-center justify-center shrink-0">
          {{ inicialUsuario }}
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-gray-900">Mi Perfil</h1>
          <p class="text-sm text-gray-500">Administra tu información de contacto</p>
        </div>
      </div>

      <!-- BANNER: perfil incompleto -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div
          v-if="mostrarBannerIncompleto"
          class="mt-6 flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-2xl p-4"
        >
          <span class="text-lg leading-none">💡</span>
          <div class="flex-1">
            <p class="font-bold">Completa la información de tu perfil</p>
            <p class="text-xs text-amber-700 mt-0.5">
              Agrega un teléfono de contacto para que podamos ubicarte más fácilmente sobre tus compras y boletos.
            </p>
          </div>
        </div>
      </Transition>

      <!-- MENSAJES -->
      <div v-if="mensajeExito" class="mt-6 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-sm rounded-r-xl">
        {{ mensajeExito }}
      </div>
      <div v-if="errorGeneral" class="mt-6 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl">
        {{ errorGeneral }}
      </div>

      <form @submit.prevent="guardarPerfil" class="mt-6 space-y-5">
        <!-- Correo (solo lectura) -->
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Correo electrónico</label>
          <p class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm text-gray-500 font-mono">
            {{ user?.correo }}
          </p>
        </div>

        <!-- Rol (solo lectura) -->
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Rol de usuario</label>
          <span class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 font-bold text-xs rounded-full capitalize">
            {{ user?.rol || 'cliente' }}
          </span>
        </div>

        <!-- Nombre -->
        <div>
          <label for="nombre" class="block text-xs font-bold text-gray-700 mb-1.5">
            Nombre completo <span class="text-red-500">*</span>
          </label>
          <input
            id="nombre"
            :value="form.nombre"
            type="text"
            maxlength="255"
            :class="campoClase('nombre')"
            @keydown="onKeydownNombre"
            @input="form.nombre = sanearNombre($event.target.value)"
            @blur="validarCampo('nombre')"
          />
          <p v-if="errores.nombre" class="mt-1 text-xs text-red-600">{{ errores.nombre }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
          <!-- Teléfono -->
          <div>
            <label for="telefono" class="block text-xs font-bold text-gray-700 mb-1.5">
              Teléfono <span class="text-gray-400 font-medium normal-case">(opcional)</span>
            </label>
            <input
              id="telefono"
              :value="form.telefono"
              type="tel"
              inputmode="numeric"
              maxlength="10"
              placeholder="5512345678"
              :class="campoClase('telefono')"
              @keydown="onKeydownTelefono"
              @input="form.telefono = sanearTelefono($event.target.value)"
              @blur="validarCampo('telefono')"
            />
            <p v-if="errores.telefono" class="mt-1 text-xs text-red-600">{{ errores.telefono }}</p>
          </div>

          <!-- RFC -->
          <div>
            <label for="rfc" class="block text-xs font-bold text-gray-700 mb-1.5">
              RFC / ID fiscal <span class="text-gray-400 font-medium normal-case">(opcional)</span>
            </label>
            <input
              id="rfc"
              :value="form.rfc"
              type="text"
              maxlength="13"
              placeholder="XAXX010101000"
              :class="campoClase('rfc') + ' uppercase'"
              @keydown="onKeydownRfc"
              @input="form.rfc = sanearRfc($event.target.value)"
              @blur="validarCampo('rfc')"
            />
            <p v-if="errores.rfc" class="mt-1 text-xs text-red-600">{{ errores.rfc }}</p>
          </div>
        </div>

        <button
          type="submit"
          :disabled="guardando"
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 active:scale-[0.99] disabled:opacity-50 transition-all cursor-pointer"
        >
          {{ guardando ? 'Guardando...' : 'Guardar cambios' }}
        </button>
      </form>

      <div class="mt-8 pt-6 border-t border-gray-100">
        <button
          type="button"
          @click="logout"
          class="w-full sm:w-auto px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 font-bold text-sm rounded-xl transition-colors cursor-pointer"
        >
          🚪 Cerrar Sesión
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { useAuth, axios } from '../composables/useAuth';
import {
  onKeydownTelefono,
  onKeydownRfc,
  onKeydownNombre,
  sanearTelefono,
  sanearRfc,
  sanearNombre,
  PATRON_TELEFONO,
  PATRON_RFC,
  PATRON_NOMBRE,
} from '../composables/useSaneoFormulario';

const { user, logout, fetchUser } = useAuth();

const inicialUsuario = computed(() => (user.value?.nombre || 'U').trim().charAt(0).toUpperCase());

// Nudge suave: falta el teléfono. El RFC es opcional para cualquier rol (no
// todo cliente individual tiene uno) así que no condiciona el banner.
const mostrarBannerIncompleto = computed(() => !!user.value && !user.value.telefono);

const form = reactive({ nombre: '', telefono: '', rfc: '' });
const errores = reactive({});
const guardando = ref(false);
const mensajeExito = ref('');
const errorGeneral = ref('');

// Sincroniza el formulario cada vez que el usuario cargado cambia (primera
// carga, o después de guardar y refrescar con fetchUser()).
watch(
  () => user.value,
  (u) => {
    if (!u) return;
    form.nombre = u.nombre || '';
    form.telefono = u.telefono || '';
    form.rfc = u.rfc || '';
  },
  { immediate: true }
);

const REGLAS = {
  nombre: (v) => {
    if (!v?.trim()) return 'El nombre es obligatorio.';
    if (!PATRON_NOMBRE.test(v)) return 'Solo letras y espacios (sin números ni símbolos).';
    return '';
  },
  telefono: (v) => {
    if (!v) return ''; // opcional
    if (!PATRON_TELEFONO.test(v)) return 'El teléfono debe tener exactamente 10 dígitos.';
    return '';
  },
  rfc: (v) => {
    if (!v) return ''; // opcional
    if (!PATRON_RFC.test(v)) return 'El RFC debe tener entre 12 y 13 caracteres (solo letras y números).';
    return '';
  },
};

const validarCampo = (campo) => {
  const mensaje = REGLAS[campo]?.(form[campo]) || '';
  if (mensaje) {
    errores[campo] = mensaje;
    return false;
  }
  delete errores[campo];
  return true;
};

const validarTodo = () => Object.keys(REGLAS).map(validarCampo).every(Boolean);

const campoClase = (campo) => [
  'w-full px-4 py-3 bg-gray-50 border rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 transition-all',
  errores[campo] ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-indigo-600',
].join(' ');

const guardarPerfil = async () => {
  mensajeExito.value = '';
  errorGeneral.value = '';

  if (!validarTodo()) {
    errorGeneral.value = 'Revisa los campos marcados antes de continuar.';
    return;
  }

  guardando.value = true;
  try {
    await axios.patch('/api/user/perfil', {
      nombre: form.nombre,
      telefono: form.telefono || null,
      rfc: form.rfc || null,
    });

    // Refresca el estado compartido (Navbar, etc.) con los datos ya guardados.
    await fetchUser();
    mensajeExito.value = 'Perfil actualizado correctamente.';
  } catch (err) {
    const data = err.response?.data;
    if (err.response?.status === 422 && data?.errors) {
      Object.entries(data.errors).forEach(([campo, mensajes]) => {
        errores[campo] = mensajes[0];
      });
      errorGeneral.value = 'Revisa los campos marcados antes de continuar.';
    } else {
      errorGeneral.value = data?.message || 'No se pudo actualizar el perfil. Intenta de nuevo.';
    }
  } finally {
    guardando.value = false;
  }
};
</script>
