<template>
  <div class="space-y-6">
    <div v-if="mensajeExito" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex justify-between items-center text-sm">
      <span>{{ mensajeExito }}</span>
      <button @click="mensajeExito = ''" class="font-bold text-emerald-800 cursor-pointer">✕</button>
    </div>
    <div v-if="mensajeError" class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl flex justify-between items-center text-sm">
      <span>{{ mensajeError }}</span>
      <button @click="mensajeError = ''" class="font-bold text-rose-800 cursor-pointer">✕</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- SUB-PANEL: TAQUILLAS -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 flex items-center gap-2 mb-4">
          <span>🏧</span> Cajas / Taquillas
        </h2>

        <form @submit.prevent="crearTaquilla" class="bg-gray-50 rounded-xl p-4 mb-5 space-y-3">
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Recinto</label>
            <select
              v-model="formTaquilla.teatro_id"
              required
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            >
              <option value="" disabled>Selecciona un recinto...</option>
              <option v-for="t in teatros" :key="t.id" :value="t.id">{{ t.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nombre de la caja</label>
            <input
              v-model.trim="formTaquilla.nombre"
              type="text"
              required
              placeholder="Ej. Caja Principal Acceso Norte"
              :class="[
                'w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2',
                formTaquilla.nombre && !nombreTaquillaValido ? 'border-rose-400 focus:ring-rose-400 bg-rose-50' : 'border-gray-200 focus:ring-indigo-500/20'
              ]"
            />
            <p v-if="formTaquilla.nombre && !nombreTaquillaValido" class="text-[10px] text-rose-600 font-semibold mt-1">
              {{ MSG_NOMBRE_CAJA }}
            </p>
          </div>
          <button
            type="submit"
            :disabled="creandoTaquilla || teatros.length === 0 || !nombreTaquillaValido"
            class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-bold rounded-lg cursor-pointer disabled:cursor-not-allowed"
          >
            {{ creandoTaquilla ? 'Creando...' : '➕ Nueva Taquilla' }}
          </button>
          <p v-if="teatros.length === 0" class="text-[11px] text-gray-400 text-center">
            Registra un recinto primero en la pestaña "Mis Recintos".
          </p>
        </form>

        <div v-if="cargandoTaquillas" class="text-center py-8 text-xs text-gray-400">Cargando taquillas...</div>
        <div v-else-if="taquillas.length === 0" class="text-center py-8 text-xs text-gray-400 italic">
          Aún no tienes ninguna taquilla registrada.
        </div>
        <div v-else class="space-y-2 max-h-96 overflow-y-auto">
          <div
            v-for="t in taquillas"
            :key="t.id"
            class="flex items-center justify-between gap-3 bg-gray-50 border border-gray-100 rounded-xl px-3.5 py-2.5"
          >
            <div class="min-w-0">
              <p class="text-sm font-bold text-gray-900 truncate">{{ t.nombre }}</p>
              <p class="text-[11px] text-gray-500 truncate">📍 {{ t.teatro?.nombre }}</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <button
                type="button"
                @click="abrirEdicionTaquilla(t)"
                class="px-2 py-1.5 text-xs hover:bg-gray-200 rounded-lg cursor-pointer"
                title="Editar nombre"
              >✏️</button>
              <button
                type="button"
                @click="toggleActiva(t)"
                :disabled="actualizandoId === t.id"
                :class="[
                  'px-2.5 py-1 text-[10px] font-bold rounded-full transition-all cursor-pointer disabled:cursor-not-allowed',
                  t.activa ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-200 text-gray-500 hover:bg-gray-300'
                ]"
              >
                {{ t.activa ? '● Activa' : '○ Inactiva' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- SUB-PANEL: CAJEROS -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 flex items-center gap-2 mb-4">
          <span>👤</span> Cajeros
        </h2>

        <form @submit.prevent="crearCajero" class="bg-gray-50 rounded-xl p-4 mb-5 space-y-3">
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nombre</label>
            <input
              v-model.trim="formCajero.nombre"
              type="text"
              required
              placeholder="Nombre del cajero"
              :class="[
                'w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2',
                formCajero.nombre && !nombreCajeroValido ? 'border-rose-400 focus:ring-rose-400 bg-rose-50' : 'border-gray-200 focus:ring-indigo-500/20'
              ]"
            />
            <p v-if="formCajero.nombre && !nombreCajeroValido" class="text-[10px] text-rose-600 font-semibold mt-1">
              {{ MSG_NOMBRE_PERSONA }}
            </p>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Correo</label>
            <input
              v-model.trim="formCajero.correo"
              type="email"
              required
              placeholder="cajero@correo.com"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Taquilla asignada (opcional)</label>
            <select
              v-model="formCajero.taquilla_id"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            >
              <option value="">Sin asignar</option>
              <option v-for="t in taquillas" :key="t.id" :value="t.id">{{ t.nombre }} — {{ t.teatro?.nombre }}</option>
            </select>
          </div>
          <button
            type="submit"
            :disabled="creandoCajero || !nombreCajeroValido"
            class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white text-xs font-bold rounded-lg cursor-pointer disabled:cursor-not-allowed"
          >
            {{ creandoCajero ? 'Creando...' : '➕ Registrar Cajero' }}
          </button>
        </form>

        <div v-if="cargandoCajeros" class="text-center py-8 text-xs text-gray-400">Cargando cajeros...</div>
        <div v-else-if="cajeros.length === 0" class="text-center py-8 text-xs text-gray-400 italic">
          Aún no tienes ningún cajero registrado.
        </div>
        <div v-else class="space-y-2 max-h-96 overflow-y-auto">
          <div
            v-for="c in cajeros"
            :key="c.id"
            class="flex items-center justify-between gap-3 bg-gray-50 border border-gray-100 rounded-xl px-3.5 py-2.5"
          >
            <div class="min-w-0">
              <p class="text-sm font-bold text-gray-900 truncate">{{ c.nombre }}</p>
              <p class="text-[11px] text-gray-500 truncate">{{ c.correo }}</p>
              <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-50 text-indigo-700">
                {{ c.taquilla ? c.taquilla.nombre : 'Sin asignar' }}
              </span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="abrirEdicionCajero(c)"
                class="px-2 py-1.5 text-xs hover:bg-gray-200 rounded-lg cursor-pointer"
                title="Editar"
              >✏️</button>
              <button
                type="button"
                @click="resetearPasswordCajero(c)"
                :disabled="reseteandoId === c.id"
                class="px-2 py-1.5 text-xs hover:bg-amber-100 rounded-lg cursor-pointer disabled:opacity-50"
                title="Restablecer contraseña"
              >🔑</button>
              <button
                type="button"
                @click="deshabilitarCajero(c)"
                :disabled="deshabilitandoId === c.id"
                class="px-2 py-1.5 text-xs hover:bg-rose-100 rounded-lg cursor-pointer disabled:opacity-50"
                title="Deshabilitar cuenta"
              >🚫</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: CREDENCIALES DEL CAJERO RECIÉN CREADO -->
    <Teleport to="body">
      <div v-if="credencialesNuevas" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
          <div class="text-center mb-4">
            <div class="text-3xl mb-1">✅</div>
            <h3 class="text-base font-black text-gray-900">Cajero registrado</h3>
            <p class="text-xs text-gray-500 mt-1">
              Comparte estas credenciales con tu cajero — la contraseña solo se muestra esta vez, no queda guardada en ningún otro lugar.
            </p>
          </div>

          <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-xs font-mono">
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">Correo:</span>
              <span class="font-bold text-gray-900 break-all text-right">{{ credencialesNuevas.correo }}</span>
            </div>
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">Contraseña:</span>
              <span class="font-bold text-gray-900">{{ credencialesNuevas.password_inicial }}</span>
            </div>
          </div>

          <button
            type="button"
            @click="credencialesNuevas = null"
            class="w-full mt-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl cursor-pointer"
          >
            Entendido
          </button>
        </div>
      </div>
    </Teleport>

    <!-- MODAL: EDITAR TAQUILLA -->
    <Teleport to="body">
      <div v-if="editandoTaquilla" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
          <h3 class="text-base font-black text-gray-900 mb-4">Editar taquilla</h3>
          <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nombre de la caja</label>
          <input
            v-model.trim="editandoTaquilla.nombre"
            type="text"
            :class="[
              'w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 mb-1',
              editandoTaquilla.nombre && !nombreEditTaquillaValido ? 'border-rose-400 focus:ring-rose-400 bg-rose-50' : 'border-gray-200 focus:ring-indigo-500/20'
            ]"
          />
          <p v-if="editandoTaquilla.nombre && !nombreEditTaquillaValido" class="text-[10px] text-rose-600 font-semibold mb-3">
            {{ MSG_NOMBRE_CAJA }}
          </p>
          <div class="flex gap-2" :class="editandoTaquilla.nombre && !nombreEditTaquillaValido ? '' : 'mt-4'">
            <button type="button" @click="editandoTaquilla = null" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl cursor-pointer">
              Cancelar
            </button>
            <button
              type="button"
              @click="guardarEdicionTaquilla"
              :disabled="guardandoEdicion || !nombreEditTaquillaValido"
              class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-bold rounded-xl cursor-pointer"
            >
              {{ guardandoEdicion ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MODAL: EDITAR CAJERO -->
    <Teleport to="body">
      <div v-if="editandoCajero" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 space-y-3">
          <h3 class="text-base font-black text-gray-900 mb-1">Editar cajero</h3>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Nombre</label>
            <input
              v-model.trim="editandoCajero.nombre"
              type="text"
              :class="[
                'w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2',
                editandoCajero.nombre && !nombreEditCajeroValido ? 'border-rose-400 focus:ring-rose-400 bg-rose-50' : 'border-gray-200 focus:ring-indigo-500/20'
              ]"
            />
            <p v-if="editandoCajero.nombre && !nombreEditCajeroValido" class="text-[10px] text-rose-600 font-semibold mt-1">
              {{ MSG_NOMBRE_PERSONA }}
            </p>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Correo</label>
            <input v-model.trim="editandoCajero.correo" type="email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Taquilla asignada</label>
            <select v-model="editandoCajero.taquilla_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
              <option value="">Sin asignar</option>
              <option v-for="t in taquillas" :key="t.id" :value="t.id">{{ t.nombre }} — {{ t.teatro?.nombre }}</option>
            </select>
          </div>
          <div class="flex gap-2 pt-2">
            <button type="button" @click="editandoCajero = null" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl cursor-pointer">
              Cancelar
            </button>
            <button
              type="button"
              @click="guardarEdicionCajero"
              :disabled="guardandoEdicion || !nombreEditCajeroValido"
              class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-bold rounded-xl cursor-pointer"
            >
              {{ guardandoEdicion ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MODAL: CONTRASEÑA DE CAJERO RESTABLECIDA -->
    <Teleport to="body">
      <div v-if="nuevaPasswordCajero" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
          <div class="text-center mb-4">
            <div class="text-3xl mb-1">🔑</div>
            <h3 class="text-base font-black text-gray-900">Contraseña restablecida</h3>
            <p class="text-xs text-gray-500 mt-1">
              Comparte la nueva contraseña con el cajero — solo se muestra esta vez.
            </p>
          </div>

          <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-xs font-mono">
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">Correo:</span>
              <span class="font-bold text-gray-900 break-all text-right">{{ nuevaPasswordCajero.correo }}</span>
            </div>
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">Contraseña:</span>
              <span class="font-bold text-gray-900">{{ nuevaPasswordCajero.password_inicial }}</span>
            </div>
          </div>

          <button
            type="button"
            @click="nuevaPasswordCajero = null"
            class="w-full mt-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl cursor-pointer"
          >
            Entendido
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { axios } from '../composables/useAuth';

// 🛡️ Mismas reglas y mensajes que TaquillaController (REGEX_NOMBRE_PERSONA /
// REGEX_NOMBRE_CAJA): validación en el cliente es solo feedback inmediato para el
// organizador — el servidor sigue siendo la autoridad real, esto nunca la sustituye.
const REGEX_NOMBRE_PERSONA = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
const MSG_NOMBRE_PERSONA = 'El nombre del vendedor solo puede contener letras y espacios.';
const REGEX_NOMBRE_CAJA = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/;
const MSG_NOMBRE_CAJA = 'El nombre de la caja solo puede contener letras, números y espacios.';

const teatros = ref([]);
const taquillas = ref([]);
const cajeros = ref([]);

const cargandoTaquillas = ref(true);
const cargandoCajeros = ref(true);
const creandoTaquilla = ref(false);
const creandoCajero = ref(false);
const actualizandoId = ref(null);

const mensajeExito = ref('');
const mensajeError = ref('');
const credencialesNuevas = ref(null);

const formTaquilla = ref({ teatro_id: '', nombre: '' });
const formCajero = ref({ nombre: '', correo: '', taquilla_id: '' });

const editandoTaquilla = ref(null);
const editandoCajero = ref(null);
const nuevaPasswordCajero = ref(null);
const guardandoEdicion = ref(false);
const reseteandoId = ref(null);
const deshabilitandoId = ref(null);

const nombreTaquillaValido = computed(() =>
  formTaquilla.value.nombre.trim() !== '' && REGEX_NOMBRE_CAJA.test(formTaquilla.value.nombre.trim())
);
const nombreCajeroValido = computed(() =>
  formCajero.value.nombre.trim() !== '' && REGEX_NOMBRE_PERSONA.test(formCajero.value.nombre.trim())
);
const nombreEditTaquillaValido = computed(() =>
  !!editandoTaquilla.value && editandoTaquilla.value.nombre.trim() !== '' && REGEX_NOMBRE_CAJA.test(editandoTaquilla.value.nombre.trim())
);
const nombreEditCajeroValido = computed(() =>
  !!editandoCajero.value && editandoCajero.value.nombre.trim() !== '' && REGEX_NOMBRE_PERSONA.test(editandoCajero.value.nombre.trim())
);

const extraerError = (err) => err.response
  ? (err.response.data?.message || 'Ocurrió un error inesperado.')
  : 'Error de conexión con el servidor.';

const cargarTeatros = async () => {
  try {
    const res = await axios.get('/api/organizador/teatros');
    teatros.value = res.data || [];
  } catch (err) {
    mensajeError.value = extraerError(err);
  }
};

const cargarTaquillas = async () => {
  cargandoTaquillas.value = true;
  try {
    const res = await axios.get('/api/organizador/taquillas');
    taquillas.value = res.data || [];
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    cargandoTaquillas.value = false;
  }
};

const cargarCajeros = async () => {
  cargandoCajeros.value = true;
  try {
    const res = await axios.get('/api/organizador/cajeros');
    cajeros.value = res.data || [];
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    cargandoCajeros.value = false;
  }
};

const crearTaquilla = async () => {
  if (!nombreTaquillaValido.value) return;
  creandoTaquilla.value = true;
  mensajeError.value = '';
  try {
    await axios.post('/api/organizador/taquillas', formTaquilla.value);
    mensajeExito.value = 'Taquilla creada correctamente.';
    formTaquilla.value = { teatro_id: '', nombre: '' };
    await cargarTaquillas();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    creandoTaquilla.value = false;
  }
};

const toggleActiva = async (taquilla) => {
  actualizandoId.value = taquilla.id;
  mensajeError.value = '';
  try {
    await axios.put(`/api/organizador/taquillas/${taquilla.id}`, { activa: !taquilla.activa });
    await cargarTaquillas();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    actualizandoId.value = null;
  }
};

const crearCajero = async () => {
  if (!nombreCajeroValido.value) return;
  creandoCajero.value = true;
  mensajeError.value = '';
  try {
    const payload = {
      nombre: formCajero.value.nombre,
      correo: formCajero.value.correo,
      taquilla_id: formCajero.value.taquilla_id || null,
    };
    const res = await axios.post('/api/organizador/cajeros', payload);
    credencialesNuevas.value = res.data;
    formCajero.value = { nombre: '', correo: '', taquilla_id: '' };
    await cargarCajeros();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    creandoCajero.value = false;
  }
};

const abrirEdicionTaquilla = (t) => {
  editandoTaquilla.value = { id: t.id, nombre: t.nombre };
};

const guardarEdicionTaquilla = async () => {
  if (!nombreEditTaquillaValido.value) return;
  guardandoEdicion.value = true;
  mensajeError.value = '';
  try {
    await axios.put(`/api/organizador/taquillas/${editandoTaquilla.value.id}`, {
      nombre: editandoTaquilla.value.nombre,
    });
    editandoTaquilla.value = null;
    mensajeExito.value = 'Taquilla actualizada correctamente.';
    await cargarTaquillas();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    guardandoEdicion.value = false;
  }
};

const abrirEdicionCajero = (c) => {
  editandoCajero.value = { id: c.id, nombre: c.nombre, correo: c.correo, taquilla_id: c.taquilla_id || '' };
};

const guardarEdicionCajero = async () => {
  if (!nombreEditCajeroValido.value) return;
  guardandoEdicion.value = true;
  mensajeError.value = '';
  try {
    const payload = {
      nombre: editandoCajero.value.nombre,
      correo: editandoCajero.value.correo,
      taquilla_id: editandoCajero.value.taquilla_id || null,
    };
    await axios.put(`/api/organizador/cajeros/${editandoCajero.value.id}`, payload);
    editandoCajero.value = null;
    mensajeExito.value = 'Cajero actualizado correctamente.';
    await cargarCajeros();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    guardandoEdicion.value = false;
  }
};

const deshabilitarCajero = async (cajero) => {
  if (!confirm(`¿Deshabilitar la cuenta de ${cajero.nombre}? Ya no podrá iniciar sesión.`)) return;

  deshabilitandoId.value = cajero.id;
  mensajeError.value = '';
  try {
    await axios.delete(`/api/organizador/cajeros/${cajero.id}`);
    mensajeExito.value = 'Cajero deshabilitado correctamente.';
    await cargarCajeros();
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    deshabilitandoId.value = null;
  }
};

const resetearPasswordCajero = async (cajero) => {
  if (!confirm(`¿Generar una nueva contraseña para ${cajero.nombre}? La contraseña actual dejará de funcionar.`)) return;

  reseteandoId.value = cajero.id;
  mensajeError.value = '';
  try {
    const res = await axios.put(`/api/organizador/cajeros/${cajero.id}/contrasena`);
    nuevaPasswordCajero.value = res.data;
  } catch (err) {
    mensajeError.value = extraerError(err);
  } finally {
    reseteandoId.value = null;
  }
};

onMounted(() => {
  cargarTeatros();
  cargarTaquillas();
  cargarCajeros();
});
</script>
