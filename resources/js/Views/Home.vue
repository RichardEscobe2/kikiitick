<template>
  <div>
    <!-- ============================== HERO ============================== -->
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 text-white">
      <!-- Halos decorativos (evitan depender de una foto externa/subida): evocan el
           ambiente de luces de concierto del mockup sin un asset de imagen real. -->
      <div class="pointer-events-none absolute -top-24 -left-16 w-80 h-80 bg-fuchsia-600/30 rounded-full blur-3xl"></div>
      <div class="pointer-events-none absolute top-10 right-0 w-96 h-96 bg-indigo-500/30 rounded-full blur-3xl"></div>
      <div class="pointer-events-none absolute bottom-0 left-1/3 w-72 h-72 bg-sky-500/20 rounded-full blur-3xl"></div>

      <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-24 sm:pt-20 sm:pb-32 text-center">
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight text-balance">
          Tu próximo momento inolvidable empieza aquí
        </h1>

        <!-- BUSCADOR FLOTANTE -->
        <div class="mt-8 sm:mt-10 max-w-3xl mx-auto bg-white rounded-2xl sm:rounded-full shadow-2xl p-2 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-0 text-left">
          <div class="flex items-center flex-1 min-w-0 px-3 py-2 sm:py-2.5">
            <svg class="w-4 h-4 text-gray-400 shrink-0 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar artistas, eventos o recintos..."
              class="w-full bg-transparent border-none text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0"
              @keyup.enter="irAResultados"
            />
          </div>

          <div class="hidden sm:block w-px h-6 bg-gray-200"></div>

          <div class="relative flex items-center px-3 py-2 sm:py-2.5 shrink-0">
            <svg class="w-4 h-4 text-gray-400 shrink-0 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <select
              v-model="fechaSeleccionada"
              class="bg-transparent border-none text-sm text-gray-700 focus:outline-none focus:ring-0 cursor-pointer pr-6"
            >
              <option value="">Fecha</option>
              <option value="hoy">Hoy</option>
              <option value="semana">Esta semana</option>
              <option value="mes">Este mes</option>
            </select>
          </div>

          <div class="hidden sm:block w-px h-6 bg-gray-200"></div>

          <div class="relative flex items-center px-3 py-2 sm:py-2.5 shrink-0">
            <svg class="w-4 h-4 text-gray-400 shrink-0 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <select
              v-model="recintoSeleccionado"
              class="bg-transparent border-none text-sm text-gray-700 focus:outline-none focus:ring-0 cursor-pointer pr-6 max-w-[140px]"
            >
              <option value="">Ubicación</option>
              <option v-for="r in recintosDisponibles" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <button
            type="button"
            @click="irAResultados"
            class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl sm:rounded-full transition-all cursor-pointer shrink-0"
          >
            Buscar
          </button>
        </div>

        <!-- PILLS DE CATEGORÍA -->
        <div v-if="categoriasDisponibles.length > 0" class="mt-6 flex flex-wrap justify-center gap-2">
          <button
            type="button"
            @click="categoriaSeleccionada = ''"
            :class="[
              'px-4 py-1.5 text-xs sm:text-sm font-semibold rounded-full transition-all cursor-pointer',
              categoriaSeleccionada === '' ? 'bg-white text-indigo-700' : 'bg-white/10 text-white hover:bg-white/20'
            ]"
          >
            Todas
          </button>
          <button
            v-for="cat in categoriasDisponibles"
            :key="cat"
            type="button"
            @click="categoriaSeleccionada = cat"
            :class="[
              'px-4 py-1.5 text-xs sm:text-sm font-semibold rounded-full transition-all cursor-pointer',
              categoriaSeleccionada === cat ? 'bg-white text-indigo-700' : 'bg-white/10 text-white hover:bg-white/20'
            ]"
          >
            {{ cat }}
          </button>
        </div>
      </div>
    </section>

    <!-- ============================== CONTENIDO ============================== -->
    <div ref="resultadosRef" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
      <div v-if="errorMsg" class="mb-6 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl text-center">
        {{ errorMsg }}
      </div>

      <section>
        <div class="flex items-end justify-between gap-3 mb-6">
          <div>
            <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Sugeridos para ti</p>
            <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight mt-0.5">Eventos Destacados</h2>
          </div>
          <button
            v-if="!mostrarTodos && eventosFiltrados.length > 4"
            type="button"
            @click="mostrarTodos = true"
            class="text-sm font-bold text-indigo-600 hover:text-indigo-800 shrink-0 flex items-center gap-1 cursor-pointer"
          >
            Ver todos
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div v-if="cargando" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="n in 4" :key="n" class="h-80 rounded-2xl bg-gray-100 animate-pulse"></div>
        </div>

        <div
          v-else-if="eventosFiltrados.length > 0"
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
        >
          <EventCard
            v-for="evento in (mostrarTodos ? eventosFiltrados : eventosFiltrados.slice(0, 4))"
            :key="evento.id"
            :evento="evento"
          />
        </div>

        <div v-else class="text-center py-16 bg-white rounded-2xl border border-dashed border-gray-200">
          <p class="text-gray-500 text-base font-medium">No se encontraron eventos con esos filtros.</p>
          <button
            @click="limpiarFiltros"
            class="mt-3 px-4 py-2 bg-indigo-50 text-indigo-600 font-semibold rounded-xl text-sm hover:bg-indigo-100 transition-colors cursor-pointer"
          >
            Limpiar filtros
          </button>
        </div>
      </section>

      <!-- ============================== PRÓXIMOS EVENTOS ============================== -->
      <!-- Mismo dataset que "Destacados" (no hay un flag "destacado" real en eventos,
           ver migración) — para no repetir las mismas 4 tarjetas dos veces, esta
           sección muestra el resto del listado, ya ordenado por fecha_hora desde el
           backend, en un carrusel horizontal para variar del grid de arriba. -->
      <section v-if="eventosProximos.length > 0" class="mt-14">
        <div class="mb-6">
          <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider">No te los pierdas</p>
          <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight mt-0.5">Próximos Eventos</h2>
        </div>

        <div class="flex gap-6 overflow-x-auto snap-x snap-mandatory pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
          <div
            v-for="evento in eventosProximos"
            :key="evento.id"
            class="w-[85%] sm:w-[calc(50%-12px)] lg:w-[calc(25%-18px)] shrink-0 snap-start"
          >
            <EventCard :evento="evento" />
          </div>
        </div>
      </section>
    </div>

    <!-- ============================== POR QUÉ ELEGIR KIKIITICK ============================== -->
    <section class="bg-white border-t border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
        <div class="text-center max-w-2xl mx-auto mb-10">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Por Qué Elegir KikiiTick</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
          <!-- Compra Segura 100% -->
          <div class="text-center px-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">Compra Segura 100%</h3>
            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
              El pago se procesa directo en Mercado Pago — KikiiTick nunca recibe ni almacena los datos de tu tarjeta.
            </p>
          </div>

          <!-- Boletos Digitales Instantáneos -->
          <div class="text-center px-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">Boletos Digitales Instantáneos</h3>
            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
              Tu código QR de acceso queda listo apenas se confirma el pago — sin filas ni impresiones.
            </p>
          </div>

          <!-- Soporte 24/7 -->
          <div class="text-center px-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">Soporte 24/7</h3>
            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
              Estamos para ayudarte antes, durante y después de tu compra.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================== NEWSLETTER ============================== -->
    <section class="bg-indigo-600">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">No te pierdas ningún evento</h2>
        <p class="text-indigo-100 text-sm mt-3 max-w-xl mx-auto">
          Únete a más de 10,000 fans y recibe acceso anticipado, descuentos exclusivos y
          recomendaciones personalizadas en tu bandeja de entrada.
        </p>

        <form @submit.prevent="suscribirse" class="mt-6 flex flex-col sm:flex-row gap-2 max-w-md mx-auto">
          <input
            v-model="correoNewsletter"
            type="email"
            required
            placeholder="Tu correo electrónico"
            class="w-full flex-1 px-4 py-2.5 rounded-xl sm:rounded-full text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-white/50"
          />
          <button
            type="submit"
            class="px-6 py-2.5 bg-white hover:bg-gray-100 text-indigo-700 font-bold text-sm rounded-xl sm:rounded-full transition-all cursor-pointer shrink-0"
          >
            Suscribirme
          </button>
        </form>

        <!-- 🛡️ No hay backend de suscriptores todavía — en vez de simular un "¡listo!"
             falso, se avisa honestamente que la función está en camino. -->
        <p v-if="mostrarAvisoNewsletter" class="text-indigo-100 text-xs mt-3">
          Muy pronto podrás suscribirte aquí — esta función todavía no está conectada.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import EventCard from '../Components/EventCard.vue';
import { axios } from '../composables/useAuth';

const eventos = ref([]);
const cargando = ref(true);
const errorMsg = ref('');

const busqueda = ref('');
const categoriaSeleccionada = ref('');
const fechaSeleccionada = ref('');
const recintoSeleccionado = ref('');
const mostrarTodos = ref(false);

const resultadosRef = ref(null);
const correoNewsletter = ref('');
const mostrarAvisoNewsletter = ref(false);

const cargarEventos = async () => {
  cargando.value = true;
  try {
    const response = await axios.get('/api/eventos');
    eventos.value = response.data || [];
  } catch (error) {
    errorMsg.value = 'No se pudieron cargar los eventos. Intenta recargar la página.';
  } finally {
    cargando.value = false;
  }
};

const categoriasDisponibles = computed(() => {
  const cats = eventos.value.map(e => e.categoria).filter(Boolean);
  return [...new Set(cats)];
});

// "Ubicación" filtra por recinto (nombre del teatro): teatro_ubicacion es texto
// libre de dirección (ej. "Av Chimalhuacan s/n") y como filtro de navegación
// generaría demasiadas opciones poco útiles — el nombre del recinto es lo mismo
// que ya se muestra en cada tarjeta, así que es la granularidad más consistente.
const recintosDisponibles = computed(() => {
  const recintos = eventos.value.map(e => e.teatro_nombre).filter(Boolean);
  return [...new Set(recintos)];
});

const dentroDelRangoFecha = (fechaHoraStr) => {
  if (!fechaSeleccionada.value) return true;
  const fechaEvento = new Date(fechaHoraStr);
  const ahora = new Date();
  const limites = { hoy: 1, semana: 7, mes: 30 };
  const dias = limites[fechaSeleccionada.value] ?? Infinity;
  const limiteSuperior = new Date(ahora);
  limiteSuperior.setDate(limiteSuperior.getDate() + dias);
  return fechaEvento >= ahora && fechaEvento <= limiteSuperior;
};

const eventosFiltrados = computed(() => {
  return eventos.value.filter(evento => {
    const query = busqueda.value.toLowerCase().trim();
    const coincideTexto =
      !query ||
      evento.titulo?.toLowerCase().includes(query) ||
      evento.descripcion?.toLowerCase().includes(query) ||
      evento.teatro_nombre?.toLowerCase().includes(query);

    const coincideCategoria = !categoriaSeleccionada.value || evento.categoria === categoriaSeleccionada.value;
    const coincideRecinto = !recintoSeleccionado.value || evento.teatro_nombre === recintoSeleccionado.value;
    const coincideFecha = dentroDelRangoFecha(evento.fecha_hora);

    return coincideTexto && coincideCategoria && coincideRecinto && coincideFecha;
  });
});

// Complemento de "Destacados": el resto del listado filtrado, ya ordenado por
// fecha_hora desde el backend. Si "Ver todos" ya expandió Destacados a la
// lista completa, esta sección queda vacía a propósito (v-if en el template) —
// no tendría sentido repetir lo mismo dos veces.
const eventosProximos = computed(() => (mostrarTodos.value ? [] : eventosFiltrados.value.slice(4)));

const limpiarFiltros = () => {
  busqueda.value = '';
  categoriaSeleccionada.value = '';
  fechaSeleccionada.value = '';
  recintoSeleccionado.value = '';
};

const irAResultados = () => {
  resultadosRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const suscribirse = () => {
  mostrarAvisoNewsletter.value = true;
};

onMounted(() => {
  cargarEventos();
});
</script>
