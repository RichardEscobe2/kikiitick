<template>
  <div class="min-h-screen bg-gray-50 pb-16">

    <!-- Estado Cargando -->
    <div v-if="cargando" class="text-center py-24">
      <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
      <p class="text-sm font-medium text-gray-500">Cargando evento...</p>
    </div>

    <!-- Estado Error -->
    <div v-else-if="error" class="max-w-2xl mx-auto text-center py-20 px-6">
      <div class="text-4xl mb-3">⚠️</div>
      <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos cargar el evento</h3>
      <p class="text-xs text-gray-500 mb-4">{{ error }}</p>
      <router-link to="/" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">
        Volver al Inicio
      </router-link>
    </div>

    <template v-else>
      <!-- HERO -->
      <div class="h-72 sm:h-96 w-full bg-gray-900 relative">
        <img :src="evento?.imagen_url" :alt="evento?.titulo" class="w-full h-full object-cover opacity-70" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <span class="px-2.5 py-0.5 bg-indigo-600 text-[10px] font-bold rounded uppercase tracking-wider text-white">
              {{ evento?.categoria }}
            </span>
            <h1 class="text-3xl sm:text-4xl font-black mt-2 leading-tight text-white">{{ evento?.titulo }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-sm text-gray-200">
              <span class="flex items-center gap-1.5">📅 {{ formatearFecha(evento?.fecha_hora) }}</span>
              <span class="flex items-center gap-1.5">📍 {{ teatro?.nombre }} ({{ teatro?.ubicacion }})</span>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- COLUMNA IZQUIERDA: INFO DEL EVENTO -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Acerca del evento -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-3">
              <span>📝</span> Acerca del Evento
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
              {{ evento?.descripcion || 'Sin descripción disponible para este evento.' }}
            </p>
          </div>

          <!-- Zonas y Precios con disponibilidad -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-4">
              <span>🏟️</span> Zonas y Tarifas
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div
                v-for="zona in zonasConDisponibilidad"
                :key="zona.id"
                class="p-4 rounded-xl border-2 flex items-center justify-between gap-3"
                :style="{ borderColor: zona.color_bg }"
              >
                <div>
                  <span class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: zona.color_bg }"></span>
                    {{ zona.nombre_zona }}
                  </span>
                  <span class="text-xs font-black text-emerald-600 block mt-1">
                    ${{ formatearPrecio(zona.precio_base) }} MXN
                  </span>
                </div>
                <span
                  class="text-[10px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap"
                  :class="zona.disponibles > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400'"
                >
                  {{ zona.disponibles > 0 ? `${zona.disponibles} disponibles` : 'Agotado' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Ubicación del Recinto -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-3">
              <span>🗺️</span> Ubicación del Recinto
            </h2>
            <div class="flex items-center justify-between bg-gray-50 rounded-xl p-4">
              <div>
                <p class="text-sm font-bold text-gray-900">{{ teatro?.nombre }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ teatro?.ubicacion }}</p>
              </div>
              <a
                :href="urlMapa"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm whitespace-nowrap"
              >
                Ver Mapa 📍
              </a>
            </div>
          </div>
        </div>

        <!-- COLUMNA DERECHA: WIDGET DE SELECCIÓN -->
        <div class="space-y-6">
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 sticky top-6">
            <h3 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
              <span>🎟️</span> Elige tus Boletos
            </h3>

            <div class="mt-4 space-y-4">
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Zona</label>
                <select
                  v-model="zonaSeleccionadaId"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                >
                  <option value="" disabled>Selecciona una zona</option>
                  <option
                    v-for="zona in zonasConDisponibilidad"
                    :key="zona.id"
                    :value="zona.id"
                    :disabled="zona.disponibles === 0"
                  >
                    {{ zona.nombre_zona }} — ${{ formatearPrecio(zona.precio_base) }} MXN{{ zona.disponibles === 0 ? ' (Agotado)' : '' }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Cantidad de Boletos</label>
                <select
                  v-model.number="cantidad"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                >
                  <option v-for="n in 7" :key="n" :value="n">{{ n }} {{ n === 1 ? 'boleto' : 'boletos' }}</option>
                </select>
              </div>

              <div v-if="zonaElegida" class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 flex justify-between">
                <span>Estimado ({{ cantidad }} × ${{ formatearPrecio(zonaElegida.precio_base) }})</span>
                <span class="font-bold text-gray-900">${{ formatearPrecio(zonaElegida.precio_base * cantidad) }} MXN</span>
              </div>

              <button
                type="button"
                @click="comprarBoletos"
                :disabled="!zonaSeleccionadaId"
                class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg transition-all cursor-pointer"
              >
                Comprar Boletos
              </button>
              <p class="text-[10px] text-gray-400 text-center">
                Elegirás tus asientos exactos en el siguiente paso.
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { axios, useAuth } from '../composables/useAuth';
import { useAuthModal } from '../composables/useAuthModal';

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const { openAuthModal } = useAuthModal();

const cargando = ref(true);
const error = ref('');

const evento = ref(null);
const teatro = ref(null);
const zonas = ref([]);
const asientos = ref([]);

const zonaSeleccionadaId = ref('');
const cantidad = ref(2);

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

// ⚡ Disponibilidad por zona derivada del mismo payload de /mapa (evita un segundo
// endpoint solo para la landing; el mapa completo de asientos ya trae todo lo necesario).
const zonasConDisponibilidad = computed(() => {
  return zonas.value.map((zona) => {
    const disponibles = asientos.value.filter(
      (a) => !a.es_pasillo && String(a.zona_teatro_id) === String(zona.id) && a.estatus === 'disponible'
    ).length;
    return { ...zona, disponibles };
  });
});

const zonaElegida = computed(() =>
  zonasConDisponibilidad.value.find((z) => String(z.id) === String(zonaSeleccionadaId.value)) || null
);

const urlMapa = computed(() => {
  const query = encodeURIComponent(`${teatro.value?.nombre || ''} ${teatro.value?.ubicacion || ''}`.trim());
  return `https://www.google.com/maps/search/?api=1&query=${query}`;
});

const cargarEvento = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const response = await axios.get(`/api/eventos/${route.params.id}/mapa`);
    evento.value = response.data.evento;
    teatro.value = response.data.teatro;
    zonas.value = (response.data.zonas || []).map((z, idx) => ({
      ...z,
      color_bg: paletaColoresZona[idx % paletaColoresZona.length].bg,
    }));
    asientos.value = response.data.asientos || [];
  } catch (err) {
    error.value = err.response
      ? (err.response.data?.message || 'Error al obtener los datos del evento.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

const irACheckout = () => {
  router.push({
    name: 'EventoCheckout',
    params: { id: route.params.id },
    query: { zona: zonaSeleccionadaId.value, cantidad: cantidad.value },
  });
};

// 🛡️ Mismo interceptor "frictionless" que el resto de la app: si no hay sesión,
// abrimos el modal de auth con un callback que retoma la navegación al checkout
// apenas el usuario inicia sesión/se registra — nunca se pierde la selección hecha.
const comprarBoletos = () => {
  if (!zonaSeleccionadaId.value) return;

  if (!isAuthenticated.value) {
    openAuthModal(() => irACheckout(), 'login');
    return;
  }

  irACheckout();
};

onMounted(() => {
  cargarEvento();
});
</script>
