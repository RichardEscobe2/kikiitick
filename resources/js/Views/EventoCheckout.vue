<template>
  <div class="min-h-screen bg-gray-50 pb-8">

    <!-- STICKY HEADER: TEMPORIZADOR DE RESERVA -->
    <div
      v-if="reservaExito"
      class="sticky top-0 z-40 bg-amber-500 text-amber-950 shadow-md"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-center gap-3 text-center flex-wrap">
        <span class="text-sm font-black">⏱️ ¡Asientos retenidos!</span>
        <span class="text-sm font-bold">
          Tienes <span class="font-mono text-base">{{ tiempoFormateado }}</span> minutos para completar tu compra antes de que se liberen.
        </span>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">

      <div v-if="errorMsg" class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl">
        {{ errorMsg }}
      </div>

      <div v-if="cargando" class="text-center py-20 bg-white rounded-3xl shadow-sm border border-gray-100">
        <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
        <p class="text-sm font-medium text-gray-500">Cargando mapa interactivo del recinto...</p>
      </div>

      <div v-else-if="error" class="text-center py-16 bg-white rounded-3xl shadow-sm border border-rose-100 p-8">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos cargar el evento</h3>
        <p class="text-xs text-gray-500 mb-4">{{ error }}</p>
        <router-link :to="`/evento/${route.params.id}`" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">
          Volver al Evento
        </router-link>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- COLUMNA IZQUIERDA: MAPA INTERACTIVO -->
        <div class="lg:col-span-2 space-y-6">

          <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
              <h1 class="text-base font-black text-gray-900">{{ evento?.titulo }}</h1>
              <p class="text-xs text-gray-500">{{ teatro?.nombre }} · {{ formatearFecha(evento?.fecha_hora) }}</p>
            </div>
            <router-link :to="`/evento/${route.params.id}`" class="text-xs text-indigo-600 font-bold hover:underline shrink-0">
              ← Volver al evento
            </router-link>
          </div>

          <div v-if="sugerenciaZona" class="bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-semibold px-4 py-2.5 rounded-xl">
            💡 Selecciona {{ sugerenciaZona.cantidad }} {{ sugerenciaZona.cantidad === 1 ? 'asiento' : 'asientos' }} en la zona
            <strong>{{ sugerenciaZona.nombre }}</strong> en el plano de abajo.
          </div>

          <!-- PLANO DE ASIENTOS -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4 pb-3 border-b border-gray-100">
              <div>
                <h3 class="text-base font-bold text-gray-900">🪑 Distribución del Recinto</h3>
                <p class="text-xs text-gray-500">Selecciona tus lugares disponibles</p>
              </div>

              <!-- CONTROLES DE ZOOM / PAN -->
              <div class="flex items-center gap-1.5 bg-gray-50 rounded-xl p-1 border border-gray-100">
                <button type="button" @click="zoomOut" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-600 font-black hover:bg-gray-100 cursor-pointer">−</button>
                <span class="text-[10px] font-bold text-gray-500 w-10 text-center">{{ Math.round(zoomLevel * 100) }}%</span>
                <button type="button" @click="zoomIn" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-600 font-black hover:bg-gray-100 cursor-pointer">+</button>
                <button type="button" @click="resetZoom" class="ml-1 px-2 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-500 text-[10px] font-bold hover:bg-gray-100 cursor-pointer">Restablecer</button>
              </div>
            </div>

            <!-- Leyenda de Estados -->
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs font-medium text-gray-600 mb-4 bg-gray-50 p-3 rounded-xl">
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-indigo-600 rounded-md"></span> Seleccionado</div>
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-gray-300 rounded-md"></span> Ocupado</div>
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md border border-dashed border-gray-300"></span> Pasillo (espacio vacío)</div>
              <div class="text-[11px] text-gray-500 font-semibold">🔍 Usa los controles de zoom o arrastra para navegar el plano</div>
            </div>

            <!-- LIENZO CON ZOOM/PAN -->
            <div
              class="relative overflow-hidden rounded-xl border border-gray-100 bg-gray-50/50 select-none"
              :class="arrastrando ? 'cursor-grabbing' : 'cursor-grab'"
              style="height: 480px;"
              @mousedown="iniciarPan"
              @mousemove="moverPan"
              @mouseup="detenerPan"
              @mouseleave="detenerPan"
            >
              <div
                class="absolute inset-0 flex items-center justify-center transition-transform"
                :class="{ 'duration-200 ease-out': !arrastrando }"
                :style="{ transform: `translate(${panX}px, ${panY}px) scale(${zoomLevel})` }"
              >
                <div class="p-4">
                  <!-- ESCENARIO FRONTAL -->
                  <div v-if="teatro?.posicion_escenario !== 'centro'" class="mb-6 flex justify-center">
                    <div class="w-64 py-2 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-400 text-gray-950 text-xs font-black rounded-lg text-center shadow-md tracking-widest uppercase border-b-4 border-amber-600">
                      🎭 ESCENARIO
                    </div>
                  </div>

                  <!-- VISTA 1: ARENA / RING 360° -->
                  <svg v-if="teatro?.posicion_escenario === 'centro'" viewBox="0 0 850 850" class="w-[560px] h-[560px]">
                    <g transform="translate(425, 425)">
                      <rect x="-55" y="-55" width="110" height="110" rx="12" fill="#f59e0b" stroke="#b45309" stroke-width="4" />
                      <text x="0" y="-6" text-anchor="middle" fill="#0f172a" font-weight="900" font-size="14" letter-spacing="1">RING</text>
                      <text x="0" y="14" text-anchor="middle" fill="#0f172a" font-weight="800" font-size="11">360°</text>
                    </g>

                    <!-- Pasillos: NO se dibuja ningún nodo en su posición — el hueco
                         resultante en la secuencia de butacas del anillo ya se lee,
                         por sí solo, como el corredor real (más claro que un punto
                         gris de 3px que fácilmente se confunde con decoración). -->
                    <g v-for="(asientosFila, filaNombre) in asientosAgrupadosPorFila" :key="filaNombre">
                      <template v-for="(asiento, idx) in asientosFila" :key="asiento.id">
                        <g
                          v-if="!asiento.es_pasillo"
                          @click="toggleSeleccionAsiento(asiento)"
                          class="cursor-pointer"
                          :class="{ 'pointer-events-none': (asiento.estatus !== 'disponible' && !estaSeleccionado(asiento.id)) || reservaExito }"
                        >
                          <rect
                            :x="calcularPosicionArena(filaNombre, idx, asientosFila.length).x - 10"
                            :y="calcularPosicionArena(filaNombre, idx, asientosFila.length).y - 8"
                            width="20" height="16" rx="3.5"
                            :fill="obtenerColorAsientoSvg(asiento)"
                            :stroke="estaSeleccionado(asiento.id) ? '#1e1b4b' : '#ffffff'"
                            :stroke-width="estaSeleccionado(asiento.id) ? 2.5 : 0.8"
                          />
                          <text
                            :x="calcularPosicionArena(filaNombre, idx, asientosFila.length).x"
                            :y="calcularPosicionArena(filaNombre, idx, asientosFila.length).y + 3.5"
                            text-anchor="middle" fill="#ffffff" font-size="8.5" font-weight="bold" class="pointer-events-none"
                          >{{ asiento.numero }}</text>
                          <title>{{ `Fila ${asiento.fila} - Asiento ${asiento.numero} | Zona: ${obtenerZonaDeAsiento(asiento)?.nombre_zona || 'General'} - $${asiento.precio_base} MXN` }}</title>
                        </g>
                      </template>
                    </g>
                  </svg>

                  <!-- VISTA 2: GRID CARTESIANO -->
                  <div v-else class="space-y-2 flex flex-col items-center">
                    <div v-for="(asientosFila, filaNombre) in asientosAgrupadosPorFila" :key="filaNombre" class="flex items-center gap-2">
                      <span class="w-8 text-xs font-bold text-gray-500 text-center uppercase shrink-0 font-mono">{{ filaNombre }}</span>
                      <div class="flex items-center gap-1.5">
                        <template v-for="asiento in asientosFila" :key="asiento.id">
                          <!-- Pasillo: espaciador puramente vacío — sin botón, sin ícono,
                               sin contenido — que abre un hueco real en la fila para que
                               los asientos se vean físicamente separados en bloques
                               distintos (Bloque A | PASILLO | Bloque B), en vez de una
                               casilla con estilo propio que el ojo sigue leyendo como
                               "otro elemento de la fila". -->
                          <div v-if="asiento.es_pasillo" class="w-6 h-7 shrink-0" title="Pasillo"></div>
                          <button
                            v-else
                            @click="toggleSeleccionAsiento(asiento)"
                            :disabled="(asiento.estatus !== 'disponible' && !estaSeleccionado(asiento.id)) || reservaExito"
                            :title="`Fila ${asiento.fila} - Asiento ${asiento.numero} | Zona: ${obtenerZonaDeAsiento(asiento)?.nombre_zona || 'General'} - $${asiento.precio_base} MXN`"
                            :style="asiento.estatus === 'disponible' && !estaSeleccionado(asiento.id) ? { backgroundColor: asiento.color_zona } : {}"
                            :class="[
                              'w-7 h-7 rounded-md text-[10px] font-bold text-white transition-all flex items-center justify-center shrink-0 cursor-pointer shadow-xs',
                              estaSeleccionado(asiento.id)
                                ? '!bg-indigo-600 shadow-md ring-2 ring-indigo-400 z-10'
                                : asiento.estatus !== 'disponible'
                                  ? '!bg-gray-200 !text-gray-400 cursor-not-allowed border border-gray-300 shadow-none'
                                  : 'hover:opacity-80',
                            ]"
                          >{{ asiento.numero }}</button>
                        </template>
                      </div>
                      <span class="w-8 text-xs font-bold text-gray-500 text-center uppercase shrink-0 font-mono">{{ filaNombre }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- COLUMNA DERECHA: RESUMEN + PAGO -->
        <div class="space-y-6">
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 sticky top-24">
            <h3 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
              <span>🛒</span> Resumen de tu Orden
            </h3>

            <div class="py-4 space-y-2 min-h-[80px] max-h-[220px] overflow-y-auto">
              <div v-if="asientosSeleccionados.length === 0" class="text-center py-6 text-xs text-gray-400 italic">
                Aún no has seleccionado ningún asiento.
              </div>
              <div
                v-for="item in asientosSeleccionados"
                :key="item.id"
                class="flex items-center justify-between bg-gray-50 p-2.5 rounded-xl text-xs border border-gray-100"
              >
                <div>
                  <span class="font-bold text-gray-900 block">{{ item.etiqueta }}</span>
                  <span class="text-[10px] font-bold text-indigo-600">{{ item.nombre_zona }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-gray-800">${{ formatearPrecio(item.precio_base) }}</span>
                  <button v-if="!reservaExito" @click="quitarAsiento(item.id)" class="text-rose-500 font-bold hover:text-rose-700 cursor-pointer">✕</button>
                </div>
              </div>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-2 text-xs">
              <div class="flex justify-between text-gray-600">
                <span>Subtotal Boletos ({{ asientosSeleccionados.length }})</span>
                <span class="font-semibold">${{ formatearPrecio(subtotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-gray-600">
                <span>Cargos por Servicio</span>
                <span class="font-semibold">${{ formatearPrecio(comisionTotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-base font-black text-gray-900 pt-2 border-t border-gray-100">
                <span>Total</span>
                <span class="text-indigo-600">${{ formatearPrecio(totalGeneral) }} MXN</span>
              </div>
            </div>

            <!-- BOTÓN APARTAR (antes de reservar) -->
            <button
              v-if="!reservaExito"
              @click="procesarReserva"
              :disabled="asientosSeleccionados.length === 0 || reservando"
              class="w-full mt-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white font-bold text-sm rounded-xl transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:cursor-not-allowed"
            >
              <span>{{ reservando ? '⏳' : '🔒' }}</span> {{ reservando ? 'Apartando Asientos...' : 'Apartar y Continuar' }}
            </button>

            <!-- ACORDEÓN DE PAGO (tras reservar) -->
            <div v-else class="mt-6 pt-5 border-t border-gray-100">
              <h4 class="text-xs font-bold text-gray-700 uppercase mb-2.5">Método de Pago</h4>

              <div class="grid grid-cols-2 gap-2 mb-4">
                <button
                  type="button"
                  @click="metodoPago = 'tarjeta'"
                  :class="[
                    'py-2.5 text-xs font-bold rounded-xl border-2 transition-all cursor-pointer flex items-center justify-center gap-1.5',
                    metodoPago === 'tarjeta' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'
                  ]"
                >💳 Tarjeta</button>
                <button
                  type="button"
                  @click="metodoPago = 'oxxo'"
                  :class="[
                    'py-2.5 text-xs font-bold rounded-xl border-2 transition-all cursor-pointer flex items-center justify-center gap-1.5',
                    metodoPago === 'oxxo' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'
                  ]"
                >🏪 Efectivo</button>
              </div>

              <!-- Panel Tarjeta -->
              <div v-if="metodoPago === 'tarjeta'" class="bg-gray-50 rounded-xl p-3.5 text-[11px] text-gray-600 leading-relaxed mb-4">
                Ingresarás los datos de tu tarjeta de forma segura en la página de pago de
                <strong>Mercado Pago</strong> (nunca los recibimos ni almacenamos en KikiiTick).
                <div class="flex items-center gap-2 mt-2 text-lg">💳 🏧 🔒</div>
              </div>

              <!-- Panel OXXO -->
              <div v-else class="bg-gray-50 rounded-xl p-3.5 text-[11px] text-gray-600 leading-relaxed mb-4">
                Generarás una ficha con código de barras para pagar en efectivo en cualquier
                tienda <strong>OXXO</strong> o <strong>7-Eleven</strong>. Válida por 24 horas —
                tus asientos permanecen apartados mientras tanto.
              </div>

              <!-- BOTÓN DINÁMICO DE ACCIÓN -->
              <button
                type="button"
                @click="confirmarPago"
                :disabled="procesandoPago"
                class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-bold text-sm rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer disabled:cursor-not-allowed"
              >
                <span>{{ procesandoPago ? '⏳' : (metodoPago === 'tarjeta' ? '💳' : '🏪') }}</span>
                {{ procesandoPago
                  ? 'Procesando...'
                  : (metodoPago === 'tarjeta' ? `Pagar $${formatearPrecio(totalGeneral)} MXN` : 'Generar Ficha de Pago OXXO') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { axios, useAuth } from '../composables/useAuth';
import { useAuthModal } from '../composables/useAuthModal';

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const { openAuthModal } = useAuthModal();

const cargando = ref(true);
const error = ref('');
const errorMsg = ref('');

const evento = ref(null);
const teatro = ref(null);
const zonas = ref([]);
const asientosMaster = ref([]);
const asientosSeleccionados = ref([]);

const reservando = ref(false);
const reservaExito = ref(false);
const procesandoPago = ref(false);
const metodoPago = ref('tarjeta'); // 'tarjeta' | 'oxxo'
const tiempoRestante = ref(300);
let intervalTimer = null;

// Sugerencia de zona/cantidad traída desde EventoLanding.vue (?zona=&cantidad=)
const sugerenciaZona = ref(null);

// --- Zoom / Pan del plano de asientos ---
const zoomLevel = ref(1);
const panX = ref(0);
const panY = ref(0);
const arrastrando = ref(false);
let panInicio = { x: 0, y: 0, panX: 0, panY: 0 };

const zoomIn = () => { zoomLevel.value = Math.min(2.5, +(zoomLevel.value + 0.25).toFixed(2)); };
const zoomOut = () => { zoomLevel.value = Math.max(0.5, +(zoomLevel.value - 0.25).toFixed(2)); };
const resetZoom = () => { zoomLevel.value = 1; panX.value = 0; panY.value = 0; };

const iniciarPan = (e) => {
  arrastrando.value = true;
  panInicio = { x: e.clientX, y: e.clientY, panX: panX.value, panY: panY.value };
};
const moverPan = (e) => {
  if (!arrastrando.value) return;
  panX.value = panInicio.panX + (e.clientX - panInicio.x);
  panY.value = panInicio.panY + (e.clientY - panInicio.y);
};
const detenerPan = () => { arrastrando.value = false; };

const paletaColoresZona = [
  { bg: '#8b5cf6' }, { bg: '#2563eb' }, { bg: '#059669' },
  { bg: '#d97706' }, { bg: '#db2777' }, { bg: '#0891b2' },
];

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  return new Date(fechaStr).toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' });
};

const obtenerZonaDeAsiento = (asiento) => {
  if (!asiento) return null;
  const targetId = asiento.zona_teatro_id;
  if (targetId !== null && targetId !== undefined) {
    const encontrada = zonas.value.find((z) => String(z.id) === String(targetId));
    if (encontrada) return encontrada;
  }
  return null;
};

const calcularPosicionArena = (filaNombre, asientoIdx, totalEnFila) => {
  const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const filaIdx = Math.max(0, abecedario.indexOf(filaNombre));
  const cx = 425, cy = 425;
  const gap = 22, baseSize = 150;
  const w = baseSize + (filaIdx * gap * 2);
  const h = baseSize + (filaIdx * gap * 2);
  const perimetro = 2 * (w + h);
  const dist = ((asientoIdx + 0.5) / totalEnFila) * perimetro;
  let x = 0, y = 0;
  const top = w, right = top + h, bottom = right + w;
  if (dist <= top) { x = -w / 2 + dist; y = -h / 2; }
  else if (dist <= right) { x = w / 2; y = -h / 2 + (dist - top); }
  else if (dist <= bottom) { x = w / 2 - (dist - right); y = h / 2; }
  else { x = -w / 2; y = h / 2 - (dist - bottom); }
  return { x: cx + x, y: cy + y };
};

const obtenerColorAsientoSvg = (asiento) => {
  if (estaSeleccionado(asiento.id)) return '#4f46e5';
  if (asiento.estatus !== 'disponible') return '#d1d5db';
  return asiento.color_zona || '#059669';
};

const cargarMapa = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const response = await axios.get(`/api/eventos/${route.params.id}/mapa`);
    const data = response.data;
    evento.value = data.evento;
    teatro.value = data.teatro;
    zonas.value = (data.zonas || []).map((z, idx) => ({
      ...z,
      color_bg: paletaColoresZona[idx % paletaColoresZona.length].bg,
    }));
    asientosMaster.value = (data.asientos || []).map((asiento) => {
      const z = obtenerZonaDeAsiento({ ...asiento });
      return { ...asiento, color_zona: z ? z.color_bg : '#059669' };
    });
  } catch (err) {
    error.value = err.response
      ? (err.response.data?.message || 'Error al obtener datos del recinto.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

const asientosAgrupadosPorFila = computed(() => {
  const grupos = {};
  asientosMaster.value.forEach((asiento) => {
    if (!grupos[asiento.fila]) grupos[asiento.fila] = [];
    grupos[asiento.fila].push(asiento);
  });
  return grupos;
});

const idsSeleccionados = computed(() => new Set(asientosSeleccionados.value.map((a) => String(a.id))));
const estaSeleccionado = (asientoId) => idsSeleccionados.value.has(String(asientoId));

const toggleSeleccionAsiento = (asiento) => {
  if (reservaExito.value) return;
  const index = asientosSeleccionados.value.findIndex((a) => String(a.id) === String(asiento.id));
  if (index !== -1) {
    asientosSeleccionados.value.splice(index, 1);
  } else {
    const z = obtenerZonaDeAsiento(asiento);
    asientosSeleccionados.value.push({
      id: asiento.id,
      etiqueta: asiento.etiqueta || `Fila ${asiento.fila} - Asiento ${asiento.numero}`,
      nombre_zona: z ? z.nombre_zona : (asiento.nombre_zona || 'General'),
      precio_base: Number(asiento.precio_base || 0),
    });
  }
};

const quitarAsiento = (asientoId) => {
  if (reservaExito.value) return;
  asientosSeleccionados.value = asientosSeleccionados.value.filter((a) => String(a.id) !== String(asientoId));
};

const procesarReserva = async () => {
  if (asientosSeleccionados.value.length === 0) return;

  // 🛡️ Interceptor "frictionless": preserva la selección de asientos en memoria y
  // solo abre el modal de auth si hace falta — no se pierde nada al iniciar sesión.
  if (!isAuthenticated.value) {
    openAuthModal(() => ejecutarReserva(), 'login');
    return;
  }

  await ejecutarReserva();
};

const ejecutarReserva = async () => {
  reservando.value = true;
  errorMsg.value = '';
  try {
    await axios.post('/api/boletos/reservar', {
      evento_id: evento.value.id,
      asiento_ids: asientosSeleccionados.value.map((a) => a.id),
    });
    reservaExito.value = true;
    iniciarTemporizador();
  } catch (err) {
    if (err.response?.status === 401) {
      openAuthModal(() => ejecutarReserva(), 'login');
      return;
    }
    errorMsg.value = err.response
      ? (err.response.data?.message || 'No se pudieron reservar los asientos.')
      : 'Ocurrió un error de conexión al apartar los asientos.';
  } finally {
    reservando.value = false;
  }
};

const iniciarTemporizador = () => {
  tiempoRestante.value = 300;
  if (intervalTimer) clearInterval(intervalTimer);
  intervalTimer = setInterval(() => {
    if (tiempoRestante.value > 0) {
      tiempoRestante.value--;
    } else {
      clearInterval(intervalTimer);
      errorMsg.value = 'Tu tiempo de reserva expiró. Los asientos han sido liberados.';
      reservaExito.value = false;
      asientosSeleccionados.value = [];
      cargarMapa();
    }
  }, 1000);
};

const tiempoFormateado = computed(() => {
  const m = Math.floor(tiempoRestante.value / 60).toString().padStart(2, '0');
  const s = (tiempoRestante.value % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
});

// 💳 Tarjeta y 🏪 OXXO comparten el mismo flujo real: Mercado Pago Checkout Pro
// (redirect a init_point) es quien procesa de verdad el cobro con tarjeta o genera
// el código de barras OXXO — nunca capturamos número de tarjeta en nuestro propio
// formulario (fuera de alcance PCI), ni inventamos un código de barras nosotros
// mismos sin que un pago real lo respalde.
const confirmarPago = async () => {
  errorMsg.value = '';
  procesandoPago.value = true;
  try {
    const response = await axios.post('/api/boletos/comprar', {
      evento_id: evento.value.id,
      asiento_ids: asientosSeleccionados.value.map((a) => a.id),
      metodo_pago: metodoPago.value,
    });

    if (intervalTimer) clearInterval(intervalTimer);

    if (response.data.init_point) {
      window.location.href = response.data.init_point;
      return;
    }

    // Si por algún motivo no se pudo generar el link de pago, no perdemos la orden:
    // el usuario puede revisarla/reintentar desde la página de confirmación.
    router.push({ name: 'ConfirmacionCompra', params: { ordenId: response.data.venta_id } });
  } catch (err) {
    errorMsg.value = err.response
      ? (err.response.data?.message || 'Ocurrió un problema al procesar el pago.')
      : 'Error al conectar con el servicio de procesamiento de pago.';
  } finally {
    procesandoPago.value = false;
  }
};

const subtotal = computed(() =>
  asientosSeleccionados.value.reduce((acc, item) => acc + Number(item.precio_base || 0), 0)
);
const comisionTotal = computed(() => {
  if (!evento.value || asientosSeleccionados.value.length === 0) return 0;
  return asientosSeleccionados.value.length * Number(evento.value.comision_fija_empresa || 0);
});
const totalGeneral = computed(() => subtotal.value + comisionTotal.value);

onMounted(() => {
  if (route.query.zona) {
    const zona = zonas.value.find((z) => String(z.id) === String(route.query.zona));
    sugerenciaZona.value = {
      id: route.query.zona,
      nombre: zona?.nombre_zona || '',
      cantidad: Number(route.query.cantidad) || 1,
    };
  }
  cargarMapa().then(() => {
    if (sugerenciaZona.value && !sugerenciaZona.value.nombre) {
      const zona = zonas.value.find((z) => String(z.id) === String(sugerenciaZona.value.id));
      if (zona) sugerenciaZona.value.nombre = zona.nombre_zona;
    }
  });
});

watch(() => route.params.id, () => {
  if (intervalTimer) clearInterval(intervalTimer);
  reservaExito.value = false;
  asientosSeleccionados.value = [];
  cargarMapa();
});

onUnmounted(() => {
  if (intervalTimer) clearInterval(intervalTimer);
});
</script>
