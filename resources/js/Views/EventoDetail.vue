<template>
  <div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Estado Cargando -->
      <div v-if="cargando" class="text-center py-20 bg-white rounded-3xl shadow-sm border border-gray-100">
        <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
        <p class="text-sm font-medium text-gray-500">Cargando mapa interactivo del recinto...</p>
      </div>

      <!-- Estado Error -->
      <div v-else-if="error" class="text-center py-16 bg-white rounded-3xl shadow-sm border border-rose-100 p-8">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos cargar el evento</h3>
        <p class="text-xs text-gray-500 mb-4">{{ error }}</p>
        <router-link to="/" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">
          Volver al Inicio
        </router-link>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- COLUMNA IZQUIERDA Y CENTRAL: MAPA INTERACTIVO -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Banner y Encabezado -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="h-52 w-full bg-gray-900 relative">
              <img :src="evento?.imagen_url" :alt="evento?.titulo" class="w-full h-full object-cover opacity-80" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
              <div class="absolute bottom-4 left-6 right-6 text-white">
                <span class="px-2.5 py-0.5 bg-indigo-600 text-[10px] font-bold rounded uppercase tracking-wider">
                  {{ evento?.categoria }}
                </span>
                <h1 class="text-2xl font-black mt-1 leading-tight">{{ evento?.titulo }}</h1>
                <p class="text-xs text-gray-300 mt-1 flex items-center gap-2">
                  <span>📍 {{ teatro?.nombre }} ({{ teatro?.ubicacion }})</span>
                  <span>•</span>
                  <span>📅 {{ formatearFecha(evento?.fecha_hora) }}</span>
                </p>
              </div>
            </div>
          </div>

          <!-- BARRA DE FILTROS Y LEYENDA DE ZONAS -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                <span>🏟️</span> Zonas y Tarifas
              </h2>
              <button 
                v-if="zonaFiltroId !== null" 
                @click="zonaFiltroId = null"
                class="text-xs text-indigo-600 font-bold hover:underline"
              >
                Mostrar Todas las Zonas
              </button>
              <span v-else class="text-xs text-gray-400 font-medium">Haz clic en una zona para resaltarla en el mapa</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <button
                v-for="zona in zonas"
                :key="zona.id"
                @click="toggleFiltroZona(zona.id)"
                :style="{ borderColor: zona.color_bg }"
                :class="[
                  'p-3 rounded-xl border-2 transition-all flex flex-col items-center justify-center text-center cursor-pointer relative overflow-hidden',
                  String(zonaFiltroId) === String(zona.id)
                    ? 'bg-gray-900 text-white shadow-lg scale-105' 
                    : 'bg-gray-50 text-gray-800 hover:bg-white'
                ]"
              >
                <!-- Indicador de Color de Zona -->
                <span 
                  class="w-3.5 h-3.5 rounded-full absolute top-2 right-2 border-2 border-white shadow-xs"
                  :style="{ backgroundColor: zona.color_bg }"
                ></span>

                <span class="text-xs font-bold">{{ zona.nombre_zona }}</span>
                <span class="text-[10px] opacity-80 mt-0.5 font-mono font-semibold">
                  Filas {{ zona.fila_inicio }} - {{ zona.fila_fin }}
                </span>
                <span class="text-xs font-black mt-1 text-emerald-600">
                  ${{ formatearPrecio(zona.precio_base) }} MXN
                </span>
              </button>
            </div>
          </div>

          <!-- PLANO MAESTRO DE ASIENTOS -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4 pb-3 border-b border-gray-100">
              <div>
                <h3 class="text-base font-bold text-gray-900">
                  🪑 Distribución del Recinto
                  <span v-if="zonaFiltroId !== null" class="text-indigo-600 text-sm ml-1">(Filtrado por Zona)</span>
                </h3>
                <p class="text-xs text-gray-500">Selecciona tus lugares disponibles</p>
              </div>
            </div>

            <!-- ESCENARIO FRONTAL (SOLO SI ES "ARRIBA") -->
            <div v-if="teatro?.posicion_escenario !== 'centro'" class="mb-8 flex justify-center">
              <div class="w-3/4 py-2 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-400 text-gray-950 text-xs font-black rounded-lg text-center shadow-md tracking-widest uppercase border-b-4 border-amber-600">
                🎭 ESCENARIO PRINCIPAL
              </div>
            </div>

            <!-- Leyenda de Estados -->
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs font-medium text-gray-600 mb-6 bg-gray-50 p-3 rounded-xl">
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-indigo-600 rounded-md"></span> Seleccionado</div>
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-gray-300 rounded-md"></span> Ocupado</div>
              <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 bg-gray-100 border border-dashed border-gray-400 rounded-md"></span> Pasillo</div>
              <div class="text-[11px] text-gray-500 font-semibold">* Butacas codificadas con el color de su respectiva zona</div>
            </div>

            <!-- VISTA 1: ARENA / RING 360° (SVG LEGIBLE Y ESPACIADO) -->
            <div v-if="teatro?.posicion_escenario === 'centro'" class="flex justify-center items-center py-2 overflow-x-auto">
              <svg viewBox="0 0 850 850" class="w-full max-w-2xl h-auto select-none">
                <!-- Ring Central -->
                <g transform="translate(425, 425)">
                  <rect x="-55" y="-55" width="110" height="110" rx="12" fill="#f59e0b" stroke="#b45309" stroke-width="4" />
                  <text x="0" y="-6" text-anchor="middle" fill="#0f172a" font-weight="900" font-size="14" letter-spacing="1">RING</text>
                  <text x="0" y="14" text-anchor="middle" fill="#0f172a" font-weight="800" font-size="11">360°</text>
                </g>

                <!-- Anillos Concéntricos de Butacas -->
                <g v-for="(asientosFila, filaNombre) in asientosAgrupadosPorFila" :key="filaNombre">
                  <template v-for="(asiento, idx) in asientosFila" :key="asiento.id">
                    
                    <!-- Pasillo SVG -->
                    <circle 
                      v-if="asiento.es_pasillo"
                      :cx="calcularPosicionArena(filaNombre, idx, asientosFila.length).x"
                      :cy="calcularPosicionArena(filaNombre, idx, asientosFila.length).y"
                      r="3"
                      fill="#d1d5db"
                    />

                    <!-- Silla Real (Butaca) -->
                    <g 
                      v-else
                      @click="toggleSeleccionAsiento(asiento)"
                      class="cursor-pointer"
                      :class="{ 'pointer-events-none': (asiento.estatus !== 'disponible' && !estaSeleccionado(asiento.id)) || reservaExito }"
                    >
                      <rect 
                        :x="calcularPosicionArena(filaNombre, idx, asientosFila.length).x - 10"
                        :y="calcularPosicionArena(filaNombre, idx, asientosFila.length).y - 8"
                        width="20"
                        height="16"
                        rx="3.5"
                        :fill="obtenerColorAsientoSvg(asiento)"
                        :stroke="estaSeleccionado(asiento.id) ? '#1e1b4b' : '#ffffff'"
                        :stroke-width="estaSeleccionado(asiento.id) ? 2.5 : 0.8"
                        :opacity="(!esZonaFiltrada(asiento) && !estaSeleccionado(asiento.id)) ? 0.15 : 1"
                      />
                      <text 
                        :x="calcularPosicionArena(filaNombre, idx, asientosFila.length).x"
                        :y="calcularPosicionArena(filaNombre, idx, asientosFila.length).y + 3.5"
                        text-anchor="middle"
                        fill="#ffffff"
                        font-size="8.5"
                        font-weight="bold"
                        class="pointer-events-none"
                        :opacity="(!esZonaFiltrada(asiento) && !estaSeleccionado(asiento.id)) ? 0.15 : 1"
                      >
                        {{ asiento.numero }}
                      </text>
                      <title>{{ `Fila ${asiento.fila} - Asiento ${asiento.numero} | Zona: ${obtenerZonaDeAsiento(asiento)?.nombre_zona || 'General'} - $${asiento.precio_base} MXN` }}</title>
                    </g>
                  </template>
                </g>
              </svg>
            </div>

            <!-- VISTA 2: GRID CARTESIANO (TEATRO TRADICIONAL) -->
            <div v-else class="overflow-x-auto pb-4">
              <div class="min-w-max space-y-2 flex flex-col items-center">
                <div 
                  v-for="(asientosFila, filaNombre) in asientosAgrupadosPorFila" 
                  :key="filaNombre"
                  class="flex items-center gap-2"
                >
                  <span class="w-8 text-xs font-bold text-gray-500 text-center uppercase shrink-0 font-mono">
                    {{ filaNombre }}
                  </span>

                  <div class="flex items-center gap-1.5">
                    <template v-for="asiento in asientosFila" :key="asiento.id">
                      
                      <!-- Pasillo -->
                      <div 
                        v-if="asiento.es_pasillo" 
                        class="w-7 h-7 bg-gray-100 rounded-md border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400 select-none shrink-0"
                      >
                        🚶
                      </div>

                      <!-- Silla Real con Color Dinámico de Zona -->
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
                          (!esZonaFiltrada(asiento) && !estaSeleccionado(asiento.id)) ? 'opacity-15' : 'opacity-100'
                        ]"
                      >
                        {{ asiento.numero }}
                      </button>
                    </template>
                  </div>

                  <span class="w-8 text-xs font-bold text-gray-500 text-center uppercase shrink-0 font-mono">
                    {{ filaNombre }}
                  </span>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- COLUMNA DERECHA: RESUMEN DE COMPRA Y TEMPORIZADOR -->
        <div class="space-y-6">

          <!-- Temporizador Reserva -->
          <div v-if="reservaExito" class="bg-amber-50 border border-amber-200 p-5 rounded-2xl shadow-sm text-center">
            <p class="text-xs font-bold text-amber-800">⏱️ Asientos apartados temporalmente</p>
            <p class="text-3xl font-black text-amber-900 my-1 font-mono tracking-wider">{{ tiempoFormateado }}</p>
            <p class="text-[10px] text-amber-700 mb-3">Completa tu pago antes de que expire el tiempo.</p>

            <button 
              @click="confirmarPago"
              class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
              <span>💳</span> Pagar ${{ formatearPrecio(totalGeneral) }} MXN Ahora
            </button>
          </div>

          <!-- Carrito Resumen -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 sticky top-6">
            <h3 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
              <span>🛒</span> Resumen de Selección
            </h3>

            <div class="py-4 space-y-2 min-h-[120px]">
              <div v-if="asientosSeleccionados.length === 0" class="text-center py-6 text-xs text-gray-400 italic">
                Aún no has seleccionado ningún asiento. Toca las butacas en el plano.
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
                  <button 
                    v-if="!reservaExito" 
                    @click="quitarAsiento(item.id)" 
                    class="text-rose-500 font-bold hover:text-rose-700 cursor-pointer"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>

            <!-- Desglose de Precios -->
            <div class="border-t border-gray-100 pt-4 space-y-2 text-xs">
              <div class="flex justify-between text-gray-600">
                <span>Subtotal Boletos ({{ asientosSeleccionados.length }})</span>
                <span class="font-semibold">${{ formatearPrecio(subtotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-gray-600">
                <span>Cargos por Servicio KikiiTick</span>
                <span class="font-semibold">${{ formatearPrecio(comisionTotal) }} MXN</span>
              </div>
              <div class="flex justify-between text-base font-black text-gray-900 pt-2 border-t border-gray-100">
                <span>Total Final</span>
                <span class="text-indigo-600">${{ formatearPrecio(totalGeneral) }} MXN</span>
              </div>
            </div>

            <!-- Botón Apartar -->
            <button 
              v-if="!reservaExito"
              @click="procesarReserva"
              :disabled="asientosSeleccionados.length === 0 || reservando"
              class="w-full mt-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white font-bold text-sm rounded-xl transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:cursor-not-allowed"
            >
              <span>{{ reservando ? '⏳' : '🔒' }}</span> {{ reservando ? 'Apartando Asientos...' : 'Apartar y Continuar' }}
            </button>
          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const cargando = ref(true);
const error = ref('');

const evento = ref(null);
const teatro = ref(null);
const zonas = ref([]);
const asientosMaster = ref([]);
const zonaFiltroId = ref(null);
const asientosSeleccionados = ref([]);

const reservando = ref(false);
const reservaExito = ref(false);
const tiempoRestante = ref(600);
let intervalTimer = null;

// Paleta de colores vivos para diferenciar zonas
const paletaColoresZona = [
  { bg: '#8b5cf6', border: '#7c3aed' }, // Púrpura (Cortesías / VIP)
  { bg: '#2563eb', border: '#1d4ed8' }, // Azul (VIP / Preferente)
  { bg: '#059669', border: '#047857' }, // Verde (Preferente)
  { bg: '#d97706', border: '#b45309' }, // Ámbar (General)
  { bg: '#db2777', border: '#be185d' }, // Rosa (Gradas)
  { bg: '#0891b2', border: '#0e7490' }  // Cian (Gradas B)
];

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  const f = new Date(fechaStr);
  return f.toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' });
};

// 🛡️ Helper para encontrar a qué zona pertenece un asiento (Fail-safe por ID o Rango A-Z)
const obtenerZonaDeAsiento = (asiento) => {
  if (!asiento) return null;

  // 1. Coincidencia directa por ID de Zona
  const targetId = asiento.zona_teatro_id || asiento.zona_id;
  if (targetId !== null && targetId !== undefined) {
    const encontradaPorId = zonas.value.find(z => String(z.id) === String(targetId));
    if (encontradaPorId) return encontradaPorId;
  }

  // 2. Respaldo por Rango de Filas (A - Z)
  if (asiento.fila && zonas.value.length > 0) {
    const fAsiento = String(asiento.fila).trim().toUpperCase();
    const encontradaPorRango = zonas.value.find(z => {
      if (!z.fila_inicio || !z.fila_fin) return false;
      const fIni = String(z.fila_inicio).trim().toUpperCase();
      const fFin = String(z.fila_fin).trim().toUpperCase();
      return fAsiento >= fIni && fAsiento <= fFin;
    });
    if (encontradaPorRango) return encontradaPorRango;
  }

  return null;
};

// Algoritmo de Arena Cuadrangular Concéntrica Espaciada
const calcularPosicionArena = (filaNombre, asientoIdx, totalEnFila) => {
  const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const filaIdx = Math.max(0, abecedario.indexOf(filaNombre));
  const cx = 425;
  const cy = 425;

  // Crecimiento simétrico entre capas para evitar solapamientos
  const gap = 22; 
  const baseSize = 150; 
  const w = baseSize + (filaIdx * gap * 2);
  const h = baseSize + (filaIdx * gap * 2);

  const perimetro = 2 * (w + h);
  const dist = ((asientoIdx + 0.5) / totalEnFila) * perimetro;

  let x = 0;
  let y = 0;

  const top = w;
  const right = top + h;
  const bottom = right + w;

  if (dist <= top) {
    x = -w/2 + dist;
    y = -h/2;
  } else if (dist <= right) {
    x = w/2;
    y = -h/2 + (dist - top);
  } else if (dist <= bottom) {
    x = w/2 - (dist - right);
    y = h/2;
  } else {
    x = -w/2;
    y = h/2 - (dist - bottom);
  }

  return { x: cx + x, y: cy + y };
};

const obtenerColorAsientoSvg = (asiento) => {
  if (estaSeleccionado(asiento.id)) return '#4f46e5'; // Indigo Seleccionado
  if (asiento.estatus !== 'disponible') return '#d1d5db'; // Gris Ocupado
  return asiento.color_zona || '#059669';
};

const cargarMapa = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const res = await fetch(`/api/eventos/${route.params.id}/mapa`, {
      headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();

    if (res.ok) {
      evento.value = data.evento;
      teatro.value = data.teatro;
      
      // 1. Asignar paleta de colores a las zonas
      zonas.value = (data.zonas || []).map((z, idx) => {
        const color = paletaColoresZona[idx % paletaColoresZona.length];
        return {
          ...z,
          color_bg: color.bg,
          color_border: color.border
        };
      });

      // 2. Mapear asientos asociando color, nombre de zona y precio real
      asientosMaster.value = (data.asientos || []).map(asiento => {
        const z = obtenerZonaDeAsiento(asiento);
        return {
          ...asiento,
          nombre_zona: z ? z.nombre_zona : 'General',
          color_zona: z ? z.color_bg : '#059669',
          precio_base: asiento.precio_base || (z ? z.precio_base : 0)
        };
      });
    } else {
      error.value = data.message || 'Error al obtener datos del recinto.';
    }
  } catch (err) {
    error.value = 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

const toggleFiltroZona = (zonaId) => {
  if (String(zonaFiltroId.value) === String(zonaId)) {
    zonaFiltroId.value = null;
  } else {
    zonaFiltroId.value = zonaId;
  }
};

const esZonaFiltrada = (asiento) => {
  if (zonaFiltroId.value === null) return true;
  const z = obtenerZonaDeAsiento(asiento);
  if (!z) return false;
  return String(z.id) === String(zonaFiltroId.value);
};

const asientosAgrupadosPorFila = computed(() => {
  const grupos = {};
  asientosMaster.value.forEach(asiento => {
    if (!grupos[asiento.fila]) {
      grupos[asiento.fila] = [];
    }
    grupos[asiento.fila].push(asiento);
  });
  return grupos;
});

const estaSeleccionado = (asientoId) => {
  return asientosSeleccionados.value.some(a => String(a.id) === String(asientoId));
};

const toggleSeleccionAsiento = (asiento) => {
  if (reservaExito.value) return;
  const index = asientosSeleccionados.value.findIndex(a => String(a.id) === String(asiento.id));
  if (index !== -1) {
    asientosSeleccionados.value.splice(index, 1);
  } else {
    const z = obtenerZonaDeAsiento(asiento);
    asientosSeleccionados.value.push({
      id: asiento.id,
      etiqueta: asiento.etiqueta || `Fila ${asiento.fila} - Asiento ${asiento.numero}`,
      nombre_zona: z ? z.nombre_zona : (asiento.nombre_zona || 'General'),
      precio_base: Number(asiento.precio_base || (z ? z.precio_base : 0))
    });
  }
};

const quitarAsiento = (asientoId) => {
  if (reservaExito.value) return;
  asientosSeleccionados.value = asientosSeleccionados.value.filter(a => String(a.id) !== String(asientoId));
};

const procesarReserva = async () => {
  if (asientosSeleccionados.value.length === 0) return;

  reservando.value = true;
  try {
    const response = await fetch('/api/boletos/reservar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        evento_id: evento.value.id,
        asiento_ids: asientosSeleccionados.value.map(a => a.id)
      })
    });

    const data = await response.json();

    if (response.ok) {
      reservaExito.value = true;
      iniciarTemporizador();
    } else {
      alert(data.message || 'No se pudieron reservar los asientos.');
    }
  } catch (err) {
    alert('Ocurrió un error de conexión al apartar los asientos.');
  } finally {
    reservando.value = false;
  }
};

const iniciarTemporizador = () => {
  tiempoRestante.value = 600;
  if (intervalTimer) clearInterval(intervalTimer);

  intervalTimer = setInterval(() => {
    if (tiempoRestante.value > 0) {
      tiempoRestante.value--;
    } else {
      clearInterval(intervalTimer);
      alert('Tu tiempo de reserva expiró. Los asientos han sido liberados.');
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

const confirmarPago = async () => {
  try {
    const response = await fetch('/api/boletos/comprar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        evento_id: evento.value.id,
        asiento_ids: asientosSeleccionados.value.map(a => a.id)
      })
    });

    const data = await response.json();

    if (response.ok) {
      if (intervalTimer) clearInterval(intervalTimer);
      alert(`🎉 ¡Felicidades! Compra realizada con éxito. Folio de venta #${data.venta_id}`);
      router.push({ name: 'Perfil' });
    } else {
      alert(data.message || 'Ocurrió un problema al procesar el pago.');
    }
  } catch (err) {
    alert('Error al conectar con el servicio de procesamiento de pago.');
  }
};

const subtotal = computed(() => {
  return asientosSeleccionados.value.reduce((acc, item) => acc + Number(item.precio_base || 0), 0);
});

const comisionTotal = computed(() => {
  if (!evento.value || asientosSeleccionados.value.length === 0) return 0;
  return asientosSeleccionados.value.length * Number(evento.value.comision_fija_empresa || 0);
});

const totalGeneral = computed(() => {
  return subtotal.value + comisionTotal.value;
});

onMounted(() => {
  cargarMapa();
});

onUnmounted(() => {
  if (intervalTimer) clearInterval(intervalTimer);
});
</script>