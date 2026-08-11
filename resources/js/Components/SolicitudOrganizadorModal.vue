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
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="solicitud-organizador-title"
        @click.self="cerrarSolicitudModal"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="relative w-full max-w-xl my-8 bg-white rounded-3xl shadow-2xl shadow-slate-300/50 border border-slate-200/80 overflow-hidden"
          >
            <button
              type="button"
              @click="cerrarSolicitudModal"
              aria-label="Cerrar"
              class="absolute top-4 right-4 z-10 w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer"
            >
              ✕
            </button>

            <!-- ENVIADA RECIÉN AHORA -->
            <div v-if="recienEnviada" class="text-center py-12 px-8">
              <div class="mx-auto w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mb-4 animate-[pop_0.35s_ease-out]">
                ✓
              </div>
              <h2 class="text-xl font-extrabold text-gray-900">¡Solicitud enviada!</h2>
              <p class="text-sm text-gray-500 mt-2 leading-relaxed max-w-sm mx-auto">
                Un administrador revisará los datos de tu recinto pronto. Te avisaremos por correo en cuanto tu cuenta de organizador esté aprobada.
              </p>
              <button
                type="button"
                @click="cerrarSolicitudModal"
                class="mt-6 px-8 py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all cursor-pointer"
              >
                Entendido
              </button>
            </div>

            <!-- YA HAY UNA SOLICITUD EN REVISIÓN -->
            <div v-else-if="user?.estatus_organizador === 'pendiente'" class="text-center py-12 px-8">
              <div class="mx-auto w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl mb-4">
                ⏳
              </div>
              <h2 id="solicitud-organizador-title" class="text-xl font-extrabold text-gray-900">Solicitud en revisión</h2>
              <p class="text-sm text-gray-500 mt-2 leading-relaxed max-w-sm mx-auto">
                Ya tienes una solicitud para ser organizador esperando aprobación. Te notificaremos por correo en cuanto un administrador la revise.
              </p>
              <button
                type="button"
                @click="cerrarSolicitudModal"
                class="mt-6 px-8 py-3.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition-all cursor-pointer"
              >
                Cerrar
              </button>
            </div>

            <!-- FORMULARIO DE SOLICITUD (estatus 'ninguno' o 'rechazado') -->
            <form v-else @submit.prevent="handleSolicitar" class="flex flex-col max-h-[85vh]">
              <div class="px-8 pt-8 pb-5 shrink-0 border-b border-gray-100">
                <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl mb-3">
                  🎪
                </div>
                <h2 id="solicitud-organizador-title" class="text-xl font-extrabold text-gray-900">
                  Conviértete en organizador
                </h2>
                <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
                  Cuéntanos sobre tu recinto y cómo contactarte. Un administrador revisa cada solicitud antes de activarla.
                </p>

                <div
                  v-if="user?.estatus_organizador === 'rechazado'"
                  class="mt-4 p-3 bg-red-50 border-l-4 border-red-400 text-red-700 text-xs text-left rounded-r-xl"
                >
                  Tu solicitud anterior no fue aprobada. Ajusta los datos y envíala de nuevo.
                </div>
              </div>

              <div class="px-8 py-6 space-y-7 overflow-y-auto">
                <!-- SECCIÓN: INFORMACIÓN DE CONTACTO -->
                <fieldset>
                  <legend class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                    <span class="w-5 h-5 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center text-[11px]">1</span>
                    Información de contacto
                  </legend>

                  <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                      <label for="telefono_contacto" class="block text-xs font-bold text-gray-700 mb-1.5">
                        Teléfono <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="telefono_contacto"
                        :value="form.telefono_contacto"
                        type="tel"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="5512345678"
                        :class="campoClase('telefono_contacto')"
                        @keydown="onKeydownTelefono"
                        @input="sanitizarTelefono"
                        @blur="validarCampo('telefono_contacto')"
                      />
                      <p v-if="errores.telefono_contacto" class="mt-1 text-xs text-red-600">{{ errores.telefono_contacto }}</p>
                    </div>

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
                        @input="sanitizarRfc"
                        @blur="validarCampo('rfc')"
                      />
                      <p v-if="errores.rfc" class="mt-1 text-xs text-red-600">{{ errores.rfc }}</p>
                    </div>
                  </div>
                </fieldset>

                <!-- SECCIÓN: DATOS DEL RECINTO / ENTIDAD -->
                <fieldset>
                  <legend class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                    <span class="w-5 h-5 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center text-[11px]">2</span>
                    Datos del recinto / entidad
                  </legend>

                  <div class="space-y-4">
                    <div>
                      <label for="recinto_nombre" class="block text-xs font-bold text-gray-700 mb-1.5">
                        Nombre del recinto <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="recinto_nombre"
                        :value="form.recinto_nombre"
                        type="text"
                        maxlength="250"
                        placeholder="Teatro Gran Recinto"
                        :class="campoClase('recinto_nombre')"
                        @keydown="onKeydownNombreRecinto"
                        @input="sanitizarNombreRecinto"
                        @blur="validarCampo('recinto_nombre')"
                      />
                      <p v-if="errores.recinto_nombre" class="mt-1 text-xs text-red-600">{{ errores.recinto_nombre }}</p>
                    </div>

                    <div>
                      <label for="recinto_direccion" class="block text-xs font-bold text-gray-700 mb-1.5">
                        Dirección <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="recinto_direccion"
                        v-model="form.recinto_direccion"
                        type="text"
                        maxlength="255"
                        placeholder="Av. Benito Juárez 123, Centro"
                        :class="campoClase('recinto_direccion')"
                        @blur="validarCampo('recinto_direccion')"
                      />
                      <p v-if="errores.recinto_direccion" class="mt-1 text-xs text-red-600">{{ errores.recinto_direccion }}</p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                      <div>
                        <label for="recinto_capacidad" class="block text-xs font-bold text-gray-700 mb-1.5">
                          Capacidad (personas) <span class="text-red-500">*</span>
                        </label>
                        <input
                          id="recinto_capacidad"
                          :value="form.recinto_capacidad"
                          type="text"
                          inputmode="numeric"
                          pattern="[0-9]*"
                          maxlength="6"
                          placeholder="500"
                          :class="campoClase('recinto_capacidad')"
                          @input="sanitizarCapacidad"
                          @blur="validarCampo('recinto_capacidad')"
                        />
                        <p v-if="errores.recinto_capacidad" class="mt-1 text-xs text-red-600">{{ errores.recinto_capacidad }}</p>
                      </div>
                    </div>

                    <div>
                      <label for="descripcion" class="block text-xs font-bold text-gray-700 mb-1.5">
                        Descripción <span class="text-gray-400 font-medium normal-case">(opcional)</span>
                      </label>
                      <textarea
                        id="descripcion"
                        v-model="form.descripcion"
                        rows="3"
                        maxlength="1000"
                        placeholder="Cuéntanos brevemente sobre el tipo de eventos que planeas organizar..."
                        :class="campoClase('descripcion') + ' resize-none'"
                      ></textarea>
                    </div>
                  </div>
                </fieldset>

                <div v-if="errorMsg" class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs text-left rounded-r-xl">
                  {{ errorMsg }}
                </div>
              </div>

              <div class="px-8 py-5 shrink-0 border-t border-gray-100 bg-gray-50/60">
                <button
                  type="submit"
                  :disabled="cargando"
                  class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 active:scale-[0.99] disabled:opacity-50 disabled:active:scale-100 transition-all cursor-pointer"
                >
                  {{ cargando ? 'Enviando...' : 'Enviar solicitud' }}
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue';
import { useAuth, axios } from '../composables/useAuth';
import { useSolicitudOrganizadorModal } from '../composables/useSolicitudOrganizadorModal';

const { user, fetchUser } = useAuth();
const { isOpen, cerrarSolicitudModal } = useSolicitudOrganizadorModal();

const cargando = ref(false);
const errorMsg = ref('');
const recienEnviada = ref(false);

const formInicial = () => ({
  telefono_contacto: '',
  rfc: '',
  recinto_nombre: '',
  recinto_direccion: '',
  recinto_capacidad: null,
  descripcion: '',
});

const form = reactive(formInicial());
const errores = reactive({});

// 🛡️ "Dumb-proof UX": estos patrones deben coincidir exactamente con las
// reglas del servidor (AuthController::solicitudOrganizador) — el saneo/
// bloqueo aquí es solo cosmético/inmediato para el usuario, la validación
// real y la autoridad final siempre es el backend.
const PATRON_TELEFONO = /^[0-9]{10}$/;
const PATRON_RFC = /^[A-Z0-9]{12,13}$/;
const PATRON_NOMBRE_RECINTO = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;

// --- FILTRADO INSTANTÁNEO POR TECLA (@keydown) ---
// Bloquea la tecla ANTES de que el carácter llegue a renderizarse — a
// diferencia de los @input de abajo (que limpian DESPUÉS, backstop para
// pegar/soltar texto, que no dispara keydown). Ctrl/Cmd/Alt siempre se dejan
// pasar para no romper atajos (copiar/pegar/seleccionar todo); si el usuario
// pega algo con caracteres inválidos, el @input correspondiente lo limpia
// igual, así que dejar pasar el atajo no abre ningún hueco.
const esAtajoTeclado = (e) => e.ctrlKey || e.metaKey || e.altKey;

const onKeydownTelefono = (e) => {
  if (esAtajoTeclado(e)) return;
  if (['Backspace', 'Tab', 'Delete', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
  if (!/^[0-9]$/.test(e.key)) e.preventDefault();
};

const TECLAS_NAVEGACION = ['Backspace', 'Tab', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];

const onKeydownNombreRecinto = (e) => {
  if (esAtajoTeclado(e) || TECLAS_NAVEGACION.includes(e.key)) return;
  // e.key.length === 1 descarta nombres de tecla especiales (ej. "Enter",
  // "Shift") que de otro modo el regex de un solo carácter rechazaría.
  if (e.key.length === 1 && !/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]$/.test(e.key)) e.preventDefault();
};

const onKeydownRfc = (e) => {
  if (esAtajoTeclado(e) || TECLAS_NAVEGACION.includes(e.key)) return;
  if (e.key.length === 1 && !/^[a-zA-Z0-9]$/.test(e.key)) e.preventDefault();
};

// --- SANEO DE RESPALDO (@input) --- cubre pegar/soltar texto, que no
// dispara @keydown: descarta cualquier carácter que de todos modos el
// backend rechazaría, en vez de dejar que quede escrito y enterarse hasta
// enviar el formulario.
const sanitizarTelefono = (e) => {
  form.telefono_contacto = e.target.value.replace(/[^0-9]/g, '').slice(0, 10);
};

const sanitizarRfc = (e) => {
  form.rfc = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 13);
};

const sanitizarNombreRecinto = (e) => {
  form.recinto_nombre = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '').slice(0, 250);
};

const sanitizarCapacidad = (e) => {
  const soloDigitos = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
  form.recinto_capacidad = soloDigitos ? Number(soloDigitos) : null;
};

const REGLAS = {
  telefono_contacto: (v) => {
    if (!v?.trim()) return 'El teléfono es obligatorio.';
    if (!PATRON_TELEFONO.test(v)) return 'El teléfono debe tener exactamente 10 dígitos.';
    return '';
  },
  rfc: (v) => {
    if (!v) return ''; // opcional
    if (!PATRON_RFC.test(v)) return 'El RFC debe tener entre 12 y 13 caracteres (solo letras y números).';
    return '';
  },
  recinto_nombre: (v) => {
    if (!v?.trim()) return 'El nombre del recinto es obligatorio.';
    if (!PATRON_NOMBRE_RECINTO.test(v)) return 'Solo letras y espacios (sin números ni símbolos).';
    return '';
  },
  recinto_direccion: (v) => (!v?.trim() ? 'La dirección es obligatoria.' : ''),
  recinto_capacidad: (v) => {
    if (!v || v < 1) return 'Indica una capacidad válida.';
    if (v > 100000) return 'La capacidad máxima permitida es 100,000.';
    return '';
  },
};

const validarCampo = (campo) => {
  const regla = REGLAS[campo];
  if (!regla) return true;
  const mensaje = regla(form[campo]);
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
  errores[campo]
    ? 'border-red-300 focus:ring-red-500'
    : 'border-gray-200 focus:ring-indigo-600',
].join(' ');

// Reinicia el estado visual cada vez que el modal se vuelve a abrir.
watch(isOpen, (open) => {
  if (open) {
    cargando.value = false;
    errorMsg.value = '';
    recienEnviada.value = false;
    Object.assign(form, formInicial());
    Object.keys(errores).forEach((k) => delete errores[k]);
  }
});

const handleSolicitar = async () => {
  errorMsg.value = '';

  if (!validarTodo()) {
    errorMsg.value = 'Revisa los campos marcados antes de continuar.';
    return;
  }

  cargando.value = true;
  try {
    await axios.post('/api/solicitud-organizador', form);
    await fetchUser(); // refresca estatus_organizador para el resto de la app (ej. Navbar)
    recienEnviada.value = true;
  } catch (err) {
    const data = err.response?.data;

    // Errores de validación del servidor (422): se mapean a los mismos campos
    // que la validación en vivo, para que el usuario los vea en el mismo lugar.
    if (err.response?.status === 422 && data?.errors) {
      Object.entries(data.errors).forEach(([campo, mensajes]) => {
        errores[campo] = mensajes[0];
      });
      errorMsg.value = 'Revisa los campos marcados antes de continuar.';
    } else {
      errorMsg.value = data?.message || 'No se pudo enviar la solicitud. Intenta de nuevo.';
    }
  } finally {
    cargando.value = false;
  }
};

const onEsc = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    cerrarSolicitudModal();
  }
};

onMounted(() => window.addEventListener('keydown', onEsc));
onUnmounted(() => window.removeEventListener('keydown', onEsc));
</script>

<style scoped>
@keyframes pop {
  0% { transform: scale(0.6); opacity: 0; }
  60% { transform: scale(1.08); opacity: 1; }
  100% { transform: scale(1); }
}
</style>
