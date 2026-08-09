<template>
  <div class="min-h-screen bg-gray-50 pb-10">
    <div class="bg-gray-900 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-black">🏧 Taquilla — Venta Directa</h1>
          <p class="text-xs text-gray-400">
            <template v-if="isVendedor">Cajero: {{ user?.nombre }} · Caja: {{ user?.taquilla?.nombre || 'Sin asignar' }}</template>
            <template v-else>Operando como: {{ user?.nombre }} ({{ user?.rol }})</template>
          </p>
        </div>
        <span class="text-[10px] font-bold uppercase tracking-widest bg-emerald-600 px-2.5 py-1 rounded-full">POS</span>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
      <div v-if="errorMsg" class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl">
        {{ errorMsg }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- PANEL IZQUIERDO: SELECCIÓN DE EVENTO + MAPA DE ASIENTOS -->
        <div class="lg:col-span-2 space-y-4">

          <!-- SELECTOR DE EVENTO: tarjetas agrupadas por recinto + búsqueda -->
          <template v-if="!eventoSeleccionadoId">
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">Buscar evento o recinto</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none">🔍</span>
                <input
                  :value="busquedaEvento"
                  @keydown="interceptarTeclaBusqueda"
                  @input="filtrarBusquedaEvento"
                  type="text"
                  placeholder="Ej. Concierto, Teatro Metropolitan..."
                  class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <p class="text-[10px] text-gray-400 mt-1.5">Solo letras y espacios — números y símbolos se ignoran automáticamente.</p>
            </div>

            <div v-if="cargandoEventos" class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
              <div class="inline-block animate-spin text-3xl mb-2">🎟️</div>
              <p class="text-xs font-medium text-gray-500">Cargando eventos activos...</p>
            </div>

            <div v-else-if="Object.keys(eventosAgrupadosPorTeatro).length === 0" class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100 text-xs text-gray-400">
              No se encontraron eventos activos que coincidan con tu búsqueda.
            </div>

            <div v-else class="space-y-6">
              <div v-for="(evsDelTeatro, nombreTeatro) in eventosAgrupadosPorTeatro" :key="nombreTeatro">
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                  📍 {{ nombreTeatro }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div
                    v-for="ev in evsDelTeatro"
                    :key="ev.id"
                    class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex gap-3 p-3"
                  >
                    <div class="w-20 h-20 rounded-xl bg-gray-100 shrink-0 overflow-hidden flex items-center justify-center">
                      <img v-if="ev.imagen_url" :src="ev.imagen_url" :alt="ev.titulo" class="w-full h-full object-cover" />
                      <span v-else class="text-2xl">🎭</span>
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col justify-between">
                      <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ ev.titulo }}</p>
                        <p class="text-[11px] text-gray-500">{{ formatearFecha(ev.fecha_hora) }}</p>
                        <p class="text-[11px] text-gray-400 truncate">📍 {{ ev.teatro_nombre }}</p>
                      </div>
                      <button
                        type="button"
                        @click="seleccionarEvento(ev)"
                        class="mt-1.5 self-start px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold rounded-lg cursor-pointer"
                      >
                        🏧 Aperturar Taquilla
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- EVENTO SELECCIONADO: barra compacta + mapa de asientos -->
          <template v-else>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-black text-gray-900 truncate">{{ eventoActual?.titulo }}</p>
                <p class="text-xs text-gray-500 truncate">{{ teatroActual?.nombre }} · {{ formatearFecha(eventoActual?.fecha_hora) }}</p>
              </div>
              <button
                type="button"
                @click="cambiarEvento"
                class="shrink-0 text-xs text-indigo-600 font-bold hover:underline cursor-pointer"
              >
                ← Cambiar evento
              </button>
            </div>

            <div v-if="cargandoMapa" class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
              <div class="inline-block animate-spin text-3xl mb-2">🎟️</div>
              <p class="text-xs font-medium text-gray-500">Cargando mapa del recinto...</p>
            </div>

            <div v-else-if="asientosMaster.length" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                <div>
                  <h3 class="text-sm font-bold text-gray-900">🪑 Distribución del Recinto</h3>
                  <p class="text-[11px] text-gray-500">Toca un asiento disponible para venderlo</p>
                </div>

                <!-- CONTROLES DE ZOOM / PAN -->
                <div class="flex items-center gap-1.5 bg-gray-50 rounded-xl p-1 border border-gray-100">
                  <button type="button" @click="zoomOut" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-600 font-black hover:bg-gray-100 cursor-pointer">−</button>
                  <span class="text-[10px] font-bold text-gray-500 w-10 text-center">{{ Math.round(zoomLevel * 100) }}%</span>
                  <button type="button" @click="zoomIn" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-600 font-black hover:bg-gray-100 cursor-pointer">+</button>
                  <button type="button" @click="resetZoom" class="ml-1 px-2 h-7 flex items-center justify-center rounded-lg bg-white shadow-xs text-gray-500 text-[10px] font-bold hover:bg-gray-100 cursor-pointer">Restablecer</button>
                </div>
              </div>

              <div class="flex items-center justify-between gap-3 text-xs font-medium text-gray-600 mb-4 bg-gray-50 p-3 rounded-xl flex-wrap">
                <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-indigo-600 rounded-md"></span> Seleccionado</div>
                <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-gray-300 rounded-md"></span> Ocupado</div>
                <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md" style="background:#059669"></span> Disponible</div>
                <div class="text-[11px] text-gray-500 font-semibold">🔍 Usa los controles de zoom o arrastra para navegar</div>
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
                    <div v-if="teatroActual?.posicion_escenario !== 'centro'" class="mb-6 flex justify-center">
                      <div class="w-64 py-2 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-400 text-gray-950 text-xs font-black rounded-lg text-center shadow-md tracking-widest uppercase border-b-4 border-amber-600">
                        🎭 ESCENARIO
                      </div>
                    </div>

                    <!-- VISTA 1: ARENA / RING 360° -->
                    <svg v-if="teatroActual?.posicion_escenario === 'centro'" viewBox="0 0 850 850" class="w-[560px] h-[560px]">
                      <g transform="translate(425, 425)">
                        <rect x="-55" y="-55" width="110" height="110" rx="12" fill="#f59e0b" stroke="#b45309" stroke-width="4" />
                        <text x="0" y="-6" text-anchor="middle" fill="#0f172a" font-weight="900" font-size="14" letter-spacing="1">RING</text>
                        <text x="0" y="14" text-anchor="middle" fill="#0f172a" font-weight="800" font-size="11">360°</text>
                      </g>

                      <g v-for="(asientosFila, filaNombre) in asientosAgrupadosPorFila" :key="filaNombre">
                        <template v-for="(asiento, idx) in asientosFila" :key="asiento.id">
                          <circle
                            v-if="asiento.es_pasillo"
                            :cx="calcularPosicionArena(filaNombre, idx, asientosFila.length).x"
                            :cy="calcularPosicionArena(filaNombre, idx, asientosFila.length).y"
                            r="3"
                            fill="#d1d5db"
                          />
                          <g
                            v-else
                            @click="toggleSeleccionAsiento(asiento)"
                            class="cursor-pointer"
                            :class="{ 'pointer-events-none': asiento.estatus !== 'disponible' && !estaSeleccionado(asiento.id) }"
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
                            <title>{{ `Fila ${asiento.fila} - Asiento ${asiento.numero} · $${asiento.precio_base} MXN` }}</title>
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
                            <div v-if="asiento.es_pasillo" class="w-7 h-7 bg-gray-100 rounded-md border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400 shrink-0">🚶</div>
                            <button
                              v-else
                              type="button"
                              @click="toggleSeleccionAsiento(asiento)"
                              :disabled="asiento.estatus !== 'disponible' && !estaSeleccionado(asiento.id)"
                              :title="`Fila ${asiento.fila} - Asiento ${asiento.numero} · $${asiento.precio_base} MXN`"
                              :style="asiento.estatus === 'disponible' && !estaSeleccionado(asiento.id) ? { backgroundColor: asiento.color_zona } : {}"
                              :class="[
                                'w-7 h-7 rounded-md text-[10px] font-bold text-white transition-all flex items-center justify-center shrink-0 cursor-pointer shadow-xs',
                                estaSeleccionado(asiento.id)
                                  ? '!bg-indigo-600 shadow-md ring-2 ring-indigo-400 z-10'
                                  : asiento.estatus !== 'disponible'
                                    ? '!bg-gray-200 !text-gray-400 cursor-not-allowed'
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

            <div v-else class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100 text-xs text-gray-400">
              Este evento no tiene asientos configurados.
            </div>
          </template>
        </div>

        <!-- PANEL DERECHO: RESUMEN + COBRO -->
        <div class="space-y-4">
          <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 sticky top-6">
            <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">🧾 Resumen de Venta</h3>

            <div class="py-3 space-y-2 min-h-[60px] max-h-[180px] overflow-y-auto">
              <div v-if="asientosSeleccionados.length === 0" class="text-center py-4 text-xs text-gray-400 italic">
                Sin asientos seleccionados.
              </div>
              <div
                v-for="item in asientosSeleccionados"
                :key="item.id"
                class="flex items-center justify-between bg-gray-50 p-2 rounded-lg text-xs border border-gray-100"
              >
                <div>
                  <span class="font-bold text-gray-900 block">{{ item.etiqueta }}</span>
                  <span class="text-[10px] font-bold text-indigo-600">{{ item.nombre_zona }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-gray-800">${{ formatearPrecio(item.precio_base) }}</span>
                  <button @click="quitarAsiento(item.id)" class="text-rose-500 font-bold hover:text-rose-700 cursor-pointer">✕</button>
                </div>
              </div>
            </div>

            <div class="border-t border-gray-100 pt-3 space-y-1.5 text-xs">
              <div class="flex justify-between text-gray-600">
                <span>Subtotal ({{ asientosSeleccionados.length }})</span>
                <span class="font-semibold">${{ formatearPrecio(subtotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-gray-600">
                <span>Cargo por servicio</span>
                <span class="font-semibold">${{ formatearPrecio(comisionTotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-base font-black text-gray-900 pt-2 border-t border-gray-100">
                <span>Total</span>
                <span class="text-indigo-600">${{ formatearPrecio(totalGeneral) }} MXN</span>
              </div>
            </div>

            <!-- MÉTODO DE PAGO -->
            <div class="mt-4 pt-4 border-t border-gray-100">
              <h4 class="text-[11px] font-bold text-gray-700 uppercase mb-2">Método de Pago</h4>
              <div class="grid grid-cols-2 gap-2 mb-3">
                <button
                  type="button"
                  @click="metodoPago = 'efectivo'"
                  :class="[
                    'py-2.5 text-xs font-bold rounded-xl border-2 transition-all cursor-pointer',
                    metodoPago === 'efectivo' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'
                  ]"
                >💵 Efectivo</button>
                <button
                  type="button"
                  @click="metodoPago = 'tarjeta_fisica'"
                  :class="[
                    'py-2.5 text-xs font-bold rounded-xl border-2 transition-all cursor-pointer',
                    metodoPago === 'tarjeta_fisica' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'
                  ]"
                >💳 Terminal POS</button>
              </div>

              <!-- CALCULADORA DE CAMBIO -->
              <div v-if="metodoPago === 'efectivo'" class="bg-gray-50 rounded-xl p-3 space-y-2 mb-3">
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Efectivo recibido</label>
                <input
                  v-model.number="efectivoRecibido"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <div class="flex justify-between text-xs font-bold pt-1">
                  <span :class="cambio < 0 ? 'text-rose-600' : 'text-gray-600'">
                    {{ cambio < 0 ? 'Falta' : 'Cambio' }}
                  </span>
                  <span :class="cambio < 0 ? 'text-rose-600' : 'text-emerald-700'">
                    ${{ formatearPrecio(Math.abs(cambio)) }} MXN
                  </span>
                </div>
              </div>

              <div class="mb-3">
                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">
                  Correo del cliente <span class="text-rose-500">*</span>
                  <span class="normal-case font-medium text-gray-400">(obligatorio, para el recibo)</span>
                </label>
                <input
                  v-model.trim="clienteEmail"
                  type="email"
                  required
                  placeholder="cliente@correo.com"
                  :class="[
                    'w-full border rounded-lg px-3 py-2 text-xs text-gray-800 focus:outline-none focus:ring-2',
                    intentoEnviar && !emailValido ? 'border-rose-400 focus:ring-rose-400 bg-rose-50' : 'border-gray-200 focus:ring-indigo-500'
                  ]"
                />
                <p v-if="intentoEnviar && !emailValido" class="text-[10px] text-rose-600 font-semibold mt-1">
                  Ingresa un correo válido — es la única forma en que el cliente recibirá su boleto.
                </p>
              </div>

              <button
                type="button"
                @click="procesarVenta"
                :disabled="!puedeVender || procesando"
                class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-black text-sm rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer disabled:cursor-not-allowed"
              >
                {{ procesando ? '⏳ Procesando...' : '✅ Procesar Venta de Taquilla' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: RECIBO POS -->
    <Teleport to="body">
      <div v-if="reciboVenta" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 print:bg-white print:p-0">
        <div class="bg-white rounded-2xl shadow-xl max-w-xs w-full overflow-hidden print:shadow-none print:rounded-none">
          <div id="recibo-pos" class="p-5 font-mono text-[11px] text-gray-800 leading-relaxed">
            <div class="text-center mb-3">
              <p class="text-sm font-black">KIKIITICK</p>
              <p class="text-[10px] text-gray-500">Recibo de Venta — Taquilla</p>
              <p class="text-[10px] text-gray-500">{{ reciboVenta.folio }}</p>
            </div>
            <div class="border-t border-dashed border-gray-300 my-2"></div>
            <p>{{ eventoActual?.titulo }}</p>
            <p class="text-gray-500">{{ formatearFecha(eventoActual?.fecha_hora) }}</p>
            <div class="border-t border-dashed border-gray-300 my-2"></div>
            <div v-for="b in reciboVenta.boletos" :key="b.numero_control" class="mb-1">
              <p>{{ b.seccion_pasillo }} · Fila {{ b.fila_palco }} · Asiento #{{ b.numero_asiento }}</p>
              <p class="text-gray-400">Folio: {{ b.numero_control }}</p>
            </div>
            <div class="border-t border-dashed border-gray-300 my-2"></div>
            <div class="flex justify-between"><span>Subtotal</span><span>${{ formatearPrecio(reciboVenta.monto_neto) }}</span></div>
            <div class="flex justify-between"><span>Servicio</span><span>${{ formatearPrecio(reciboVenta.total_comisiones) }}</span></div>
            <div class="flex justify-between font-black text-sm pt-1"><span>TOTAL</span><span>${{ formatearPrecio(reciboVenta.total) }}</span></div>
            <div class="border-t border-dashed border-gray-300 my-2"></div>
            <p>Pago: {{ reciboVenta.metodo_pago === 'efectivo' ? 'Efectivo' : 'Terminal POS' }}</p>
            <template v-if="reciboVenta.metodo_pago === 'efectivo'">
              <p>Recibido: ${{ formatearPrecio(reciboVenta.efectivo_recibido) }}</p>
              <p>Cambio: ${{ formatearPrecio(reciboVenta.cambio) }}</p>
            </template>
            <p class="text-gray-500 mt-1">Cajero: {{ user?.nombre || user?.correo }}</p>
            <p class="text-gray-400 text-[10px] mt-2 text-center">¡Gracias por tu compra!</p>
          </div>

          <div class="flex gap-2 p-4 pt-0 print:hidden">
            <button
              type="button"
              @click="imprimirRecibo"
              class="flex-1 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold text-xs rounded-xl cursor-pointer"
            >
              🖨️ Imprimir
            </button>
            <button
              type="button"
              @click="nuevaVenta"
              class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl cursor-pointer"
            >
              ➕ Nueva Venta
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { axios, useAuth } from '../composables/useAuth';

const { user, isVendedor } = useAuth();

// --- Selección de evento (tarjetas agrupadas por recinto + búsqueda) ---
const eventos = ref([]);
const cargandoEventos = ref(true);
const busquedaEvento = ref('');
const eventoSeleccionadoId = ref('');
const eventoActual = ref(null);
const teatroActual = ref(null);
const zonas = ref([]);
const asientosMaster = ref([]);
const cargandoMapa = ref(false);

const asientosSeleccionados = ref([]);
const metodoPago = ref('efectivo');
const efectivoRecibido = ref(null);
const clienteEmail = ref('');
const intentoEnviar = ref(false);
const procesando = ref(false);
const errorMsg = ref('');
const reciboVenta = ref(null);

const paletaColoresZona = [
  { bg: '#8b5cf6' }, { bg: '#2563eb' }, { bg: '#059669' },
  { bg: '#d97706' }, { bg: '#db2777' }, { bg: '#0891b2' },
];

// --- Zoom / Pan del plano de asientos (mismo patrón que EventoCheckout.vue) ---
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

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  return new Date(fechaStr).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
};

const cargarEventos = async () => {
  cargandoEventos.value = true;
  try {
    const response = await axios.get('/api/eventos');
    eventos.value = response.data || [];
  } catch (err) {
    errorMsg.value = 'No se pudo cargar la lista de eventos activos.';
  } finally {
    cargandoEventos.value = false;
  }
};

// 🛡️ Filtra en tiempo real cualquier carácter que no sea letra/espacio (incluye
// acentos y ñ). Nota: este campo nunca sale del navegador — eventosFiltrados()
// abajo hace un .filter() puramente local sobre los eventos ya cargados, nunca se
// envía a la API — así que esta restricción es una decisión de UX, no una
// mitigación de inyección (Vue ya escapa toda interpolación por defecto). El efecto
// colateral real es que ya no se puede buscar por dígitos (ej. "Teatro 1",
// "Vive Latino 2026") — ver el texto de ayuda bajo el input.
const REGEX_CARACTER_BUSQUEDA = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]$/;

// 🛡️ Intercepción a nivel de tecla (capa primaria): bloquea dígitos/símbolos ANTES
// de que lleguen al DOM, para que "3333.." nunca se dibuje ni un instante en el
// input. Solo se evalúan pulsaciones que producen UN carácter imprimible
// (e.key.length === 1) — esto es deliberado, no un descuido: cualquier tecla
// nombrada de más de un carácter (Backspace, Delete, Tab, ArrowLeft/Right, Home,
// End, Escape, Shift, Control, y 'Dead' —la tecla muerta que compone acentos en
// teclados Latinoamericanos, ej. ´ + a = á— ) tiene e.key.length > 1 y por lo tanto
// NUNCA entra a este bloqueo, sin necesidad de enumerarlas una por una. Enumerar
// 'Dead' a mano es justo el tipo de detalle que se olvida y rompe los acentos
// compuestos por tecla muerta — construirlo así lo evita de raíz. e.isComposing
// se respeta por la misma razón (IME/composición de acentos en curso).
const interceptarTeclaBusqueda = (e) => {
  if (e.isComposing || e.ctrlKey || e.metaKey || e.altKey) return;

  if (e.key.length === 1 && !REGEX_CARACTER_BUSQUEDA.test(e.key)) {
    e.preventDefault();
  }
};

const filtrarBusquedaEvento = (e) => {
  busquedaEvento.value = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
};

const eventosFiltrados = computed(() => {
  const q = busquedaEvento.value.trim().toLowerCase();
  if (!q) return eventos.value;
  return eventos.value.filter((ev) =>
    (ev.titulo || '').toLowerCase().includes(q) || (ev.teatro_nombre || '').toLowerCase().includes(q)
  );
});

const eventosAgrupadosPorTeatro = computed(() => {
  const grupos = {};
  eventosFiltrados.value.forEach((ev) => {
    const nombreTeatro = ev.teatro_nombre || 'Recinto sin nombre';
    if (!grupos[nombreTeatro]) grupos[nombreTeatro] = [];
    grupos[nombreTeatro].push(ev);
  });
  return grupos;
});

const obtenerZonaDeAsiento = (asiento) => {
  if (!asiento) return null;
  const targetId = asiento.zona_teatro_id;
  if (targetId !== null && targetId !== undefined) {
    return zonas.value.find((z) => String(z.id) === String(targetId)) || null;
  }
  return null;
};

const cargarMapa = async () => {
  asientosSeleccionados.value = [];
  resetZoom();
  if (!eventoSeleccionadoId.value) return;

  cargandoMapa.value = true;
  errorMsg.value = '';
  try {
    const response = await axios.get(`/api/eventos/${eventoSeleccionadoId.value}/mapa`);
    const data = response.data;
    eventoActual.value = data.evento;
    teatroActual.value = data.teatro;
    zonas.value = (data.zonas || []).map((z, idx) => ({
      ...z,
      color_bg: paletaColoresZona[idx % paletaColoresZona.length].bg,
    }));
    asientosMaster.value = (data.asientos || []).map((asiento) => {
      const z = obtenerZonaDeAsiento(asiento);
      return { ...asiento, color_zona: z ? z.color_bg : '#059669' };
    });
  } catch (err) {
    errorMsg.value = err.response
      ? (err.response.data?.message || 'Error al obtener el mapa del recinto.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargandoMapa.value = false;
  }
};

const seleccionarEvento = async (ev) => {
  eventoSeleccionadoId.value = ev.id;
  await cargarMapa();
};

const cambiarEvento = () => {
  eventoSeleccionadoId.value = '';
  eventoActual.value = null;
  teatroActual.value = null;
  asientosMaster.value = [];
  asientosSeleccionados.value = [];
  resetZoom();
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

// Distribuye asientos alrededor de un anillo cuadrado concéntrico por fila — mismo
// cálculo que EventoCheckout.vue, para que la vista "arena/ring 360°" luzca idéntica
// en ambos flujos (cliente y taquilla) para el mismo recinto.
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

const toggleSeleccionAsiento = (asiento) => {
  const index = asientosSeleccionados.value.findIndex((a) => String(a.id) === String(asiento.id));
  if (index !== -1) {
    asientosSeleccionados.value.splice(index, 1);
    return;
  }
  const z = obtenerZonaDeAsiento(asiento);
  asientosSeleccionados.value.push({
    id: asiento.id,
    etiqueta: `Fila ${asiento.fila} - Asiento ${asiento.numero}`,
    nombre_zona: z ? z.nombre_zona : 'General',
    precio_base: Number(asiento.precio_base || 0),
  });
};

const quitarAsiento = (asientoId) => {
  asientosSeleccionados.value = asientosSeleccionados.value.filter((a) => String(a.id) !== String(asientoId));
};

const subtotal = computed(() =>
  asientosSeleccionados.value.reduce((acc, item) => acc + Number(item.precio_base || 0), 0)
);
const comisionTotal = computed(() => {
  if (!eventoActual.value || asientosSeleccionados.value.length === 0) return 0;
  return asientosSeleccionados.value.length * Number(eventoActual.value.comision_fija_empresa || 0);
});
const totalGeneral = computed(() => subtotal.value + comisionTotal.value);

const cambio = computed(() => Number(efectivoRecibido.value || 0) - totalGeneral.value);

const emailValido = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clienteEmail.value.trim()));

const puedeVender = computed(() => {
  if (asientosSeleccionados.value.length === 0) return false;
  if (!emailValido.value) return false;
  if (metodoPago.value === 'efectivo') {
    return Number(efectivoRecibido.value || 0) >= totalGeneral.value;
  }
  return true;
});

const procesarVenta = async () => {
  intentoEnviar.value = true;
  if (!puedeVender.value) return;

  procesando.value = true;
  errorMsg.value = '';
  try {
    const payload = {
      evento_id: eventoSeleccionadoId.value,
      asiento_ids: asientosSeleccionados.value.map((a) => a.id),
      metodo_pago: metodoPago.value,
      cliente_email: clienteEmail.value.trim(),
    };

    const response = await axios.post('/api/boletos/comprar-pos', payload);

    reciboVenta.value = {
      ...response.data,
      efectivo_recibido: metodoPago.value === 'efectivo' ? Number(efectivoRecibido.value || 0) : null,
      cambio: metodoPago.value === 'efectivo' ? cambio.value : null,
    };

    // La venta ya quedó 'vendido' en el backend — refrescamos el mapa para no
    // permitir revender los mismos asientos desde esta misma pantalla.
    await cargarMapa();
  } catch (err) {
    errorMsg.value = err.response
      ? (err.response.data?.message || 'No se pudo procesar la venta.')
      : 'Error de conexión con el servidor.';
  } finally {
    procesando.value = false;
  }
};

const imprimirRecibo = () => {
  window.print();
};

const nuevaVenta = () => {
  reciboVenta.value = null;
  asientosSeleccionados.value = [];
  metodoPago.value = 'efectivo';
  efectivoRecibido.value = null;
  clienteEmail.value = '';
  intentoEnviar.value = false;
};

onMounted(() => {
  cargarEventos();
});
</script>

<style>
@media print {
  body * { visibility: hidden; }
  #recibo-pos, #recibo-pos * { visibility: visible; }
  #recibo-pos { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
