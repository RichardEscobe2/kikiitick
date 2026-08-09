<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-6xl mx-auto">
      <div class="mb-6">
        <h1 class="text-2xl font-black text-gray-900">🎫 Mis Boletos</h1>
        <p class="text-sm text-gray-500 mt-1">Órdenes con pago confirmado. Presenta el código QR de cada boleto en el acceso al evento.</p>
      </div>

      <!-- Cargando -->
      <div v-if="cargando" class="text-center py-20">
        <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
        <p class="text-sm font-medium text-gray-500">Cargando tus boletos...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-16 bg-white rounded-3xl shadow-sm border border-rose-100 p-8">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos cargar tus boletos</h3>
        <p class="text-xs text-gray-500">{{ error }}</p>
      </div>

      <!-- Vacío -->
      <div v-else-if="ordenes.length === 0" class="text-center py-20 bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
        <div class="text-4xl mb-3">🪑</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Aún no tienes boletos confirmados</h3>
        <p class="text-xs text-gray-500 mb-4">Cuando completes una compra, tus boletos aparecerán aquí con su código QR.</p>
        <router-link to="/" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">
          Explorar Eventos
        </router-link>
      </div>

      <!-- GRID DE ÓRDENES -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="orden in ordenes"
          :key="orden.id"
          class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden flex flex-col"
        >
          <div class="h-28 bg-gray-900 relative shrink-0">
            <img v-if="orden.evento?.imagen_url" :src="orden.evento.imagen_url" class="w-full h-full object-cover opacity-60" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            <div class="absolute bottom-3 left-4 right-4 text-white">
              <h2 class="text-sm font-black leading-tight line-clamp-1">{{ orden.evento?.titulo || 'Evento' }}</h2>
              <p class="text-[11px] text-gray-300">
                📅 {{ formatearFecha(orden.evento?.fecha_hora) }}
              </p>
            </div>
          </div>

          <div class="p-4 flex-1 flex flex-col">
            <p class="text-[11px] text-gray-400 mb-2">📍 {{ orden.evento?.teatro?.nombre || 'Recinto' }}</p>

            <div class="space-y-2 flex-1">
              <button
                v-for="boleto in orden.boletos"
                :key="boleto.numero_control"
                type="button"
                @click="abrirModal(orden, boleto)"
                class="w-full flex items-center justify-between gap-2 bg-gray-50 hover:bg-indigo-50 border border-gray-100 rounded-xl px-3 py-2 text-left transition-colors cursor-pointer"
              >
                <div class="min-w-0">
                  <p class="text-xs font-bold text-gray-900 truncate">{{ boleto.seccion_pasillo }}</p>
                  <p class="text-[11px] text-gray-500">Fila {{ boleto.fila_palco }} · Asiento #{{ boleto.numero_asiento }}</p>
                </div>
                <span class="text-[10px] font-bold text-indigo-600 whitespace-nowrap shrink-0">Ver QR ▸</span>
              </button>
            </div>

            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-[11px]">
              <span class="font-mono text-gray-400">{{ orden.folio }}</span>
              <span class="font-bold text-gray-700">${{ formatearPrecio(orden.monto_total) }} MXN</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: QR DE ENTRADA -->
    <Teleport to="body">
      <div
        v-if="modalAbierto"
        class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
        @click.self="cerrarModal"
      >
        <div class="bg-white rounded-3xl shadow-xl max-w-xs w-full overflow-hidden">
          <div class="bg-gray-900 text-white text-center py-3 relative">
            <p class="text-xs uppercase tracking-widest text-gray-300">{{ ordenSeleccionada?.evento?.titulo }}</p>
            <button
              type="button"
              @click="cerrarModal"
              class="absolute top-2 right-3 text-gray-300 hover:text-white text-lg cursor-pointer"
            >✕</button>
          </div>

          <div class="p-6 text-center">
            <div v-if="qrCargando" class="py-14 text-3xl animate-spin">🎟️</div>
            <img v-else-if="qrModalUrl" :src="qrModalUrl" alt="Código QR de entrada" class="w-48 h-48 mx-auto rounded-xl border border-gray-100" />

            <div class="mt-4 space-y-1">
              <p class="text-sm font-bold text-gray-900">{{ boletoSeleccionado?.seccion_pasillo }}</p>
              <p class="text-xs text-gray-500">Fila {{ boletoSeleccionado?.fila_palco }} · Asiento #{{ boletoSeleccionado?.numero_asiento }}</p>
              <p class="text-[10px] font-mono text-gray-400">Folio: {{ boletoSeleccionado?.numero_control }}</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import QRCode from 'qrcode';
import { axios } from '../composables/useAuth';

const cargando = ref(true);
const error = ref('');
const ordenes = ref([]);

const modalAbierto = ref(false);
const ordenSeleccionada = ref(null);
const boletoSeleccionado = ref(null);
const qrModalUrl = ref('');
const qrCargando = ref(false);

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  return new Date(fechaStr).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
};

// RF-12: QR generado 100% en el navegador a partir de Acceso.token_qr, igual que en
// ConfirmacionCompra.vue — nunca se envía a un tercero.
const abrirModal = async (orden, boleto) => {
  ordenSeleccionada.value = orden;
  boletoSeleccionado.value = boleto;
  modalAbierto.value = true;
  qrModalUrl.value = '';
  qrCargando.value = true;
  try {
    qrModalUrl.value = await QRCode.toDataURL(boleto.token_qr, {
      width: 320,
      margin: 1,
      color: { dark: '#1e1b4b', light: '#ffffff' },
    });
  } finally {
    qrCargando.value = false;
  }
};

const cerrarModal = () => {
  modalAbierto.value = false;
};

const cargarBoletos = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const response = await axios.get('/api/mis-boletos');
    ordenes.value = response.data;
  } catch (err) {
    error.value = err.response
      ? (err.response.data?.message || 'No se pudieron cargar tus boletos.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

onMounted(() => {
  cargarBoletos();
});
</script>
