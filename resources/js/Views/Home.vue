<template>
  <!-- Contenedor Principal: Centrado y con márgenes laterales responsivos -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10 pb-12">
    
    <!-- BARRA DE BÚSQUEDA Y FILTROS -->
    <section class="max-w-xl mx-auto my-2">
      <div class="flex items-center bg-white p-1.5 pl-4 rounded-full border border-gray-200 shadow-sm focus-within:ring-2 focus-within:ring-indigo-600 focus-within:border-transparent transition-all">
        <!-- Icono de Lupa -->
        <span class="text-gray-400 mr-2 text-base select-none">🔍</span>

        <!-- Input de Búsqueda -->
        <input 
          v-model="busqueda"
          type="text" 
          placeholder="Buscar evento o teatro..." 
          class="w-full bg-transparent border-none text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0 pr-2"
        />

        <!-- Botón de Filtros (Solo Icono) -->
        <div class="relative flex items-center shrink-0">
          <select 
            v-model="categoriaSeleccionada"
            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
            title="Filtrar por categoría"
          >
            <option value="">Todas las categorías</option>
            <option v-for="cat in categoriasDisponibles" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>

          <button 
            type="button"
            :class="categoriaSeleccionada ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
            class="p-2.5 rounded-full transition-colors flex items-center justify-center"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
          </button>
        </div>
      </div>
    </section>

    <!-- SECCIÓN: Próximos Eventos (Carrusel de 4 cards por fila) -->
    <section>
      <!-- Encabezado de Sección con Controles y Ver Todas -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
            <span></span> Próximos Eventos
          </h2>
          <p class="text-xs text-gray-500 mt-0.5">Explora las fechas más cercanas y asegura tus accesos.</p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Badge con total -->
          <span class="text-xs font-semibold bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full shrink-0">
            {{ eventosFiltrados.length }} disponibles
          </span>

          <!-- Botones de Desplazamiento (Solo visibles en modo carrusel) -->
          <div v-if="!mostrarTodosGrid && eventosFiltrados.length > 4" class="hidden sm:flex items-center gap-1">
            <button 
              @click="scrollCarrusel('izquierda')"
              class="p-2 bg-white border border-gray-200 rounded-full hover:bg-gray-50 text-gray-700 shadow-sm transition-all"
              title="Anterior"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button 
              @click="scrollCarrusel('derecha')"
              class="p-2 bg-white border border-gray-200 rounded-full hover:bg-gray-50 text-gray-700 shadow-sm transition-all"
              title="Siguiente"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <!-- Botón Ver Todas / Ver en Carrusel -->
          <button 
            @click="mostrarTodosGrid = !mostrarTodosGrid"
            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50/80 hover:bg-indigo-100 px-3.5 py-1.5 rounded-full transition-all flex items-center gap-1 shrink-0"
          >
            <span>{{ mostrarTodosGrid ? ' Ver carrusel ◂' : 'Ver todas ›' }}</span>
          </button>
        </div>
      </div>

      <!-- ESTADO 1: Carrusel Horizontal (4 cards por fila en LG) -->
      <div 
        v-if="eventosFiltrados.length > 0 && !mostrarTodosGrid"
        ref="carruselRef"
        class="flex gap-6 overflow-x-auto snap-x snap-mandatory scrollbar-none py-2 px-0.5 scroll-smooth"
      >
        <div 
          v-for="evento in eventosFiltrados" 
          :key="'carrusel-' + evento.id"
          class="w-[85%] sm:w-[calc(50%-12px)] md:w-[calc(33.333%-16px)] lg:w-[calc(25%-18px)] shrink-0 snap-start"
        >
          <EventCard :evento="evento" class="h-full" />
        </div>
      </div>

      <!-- ESTADO 2: Grid Completo (Cuando le das clic a "Ver todas >") -->
      <div 
        v-else-if="eventosFiltrados.length > 0 && mostrarTodosGrid" 
        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 animate-fadeIn"
      >
        <EventCard 
          v-for="evento in eventosFiltrados" 
          :key="'grid-' + evento.id" 
          :evento="evento"
          class="h-full" 
        />
      </div>

      <!-- Estado vacío -->
      <div v-else class="text-center py-12 bg-white rounded-2xl border border-dashed border-gray-200">
        <p class="text-gray-500 text-lg font-medium">No se encontraron eventos con esa búsqueda.</p>
        <button 
          @click="limpiarFiltros" 
          class="mt-3 px-4 py-2 bg-indigo-50 text-indigo-600 font-semibold rounded-xl text-sm hover:bg-indigo-100 transition-colors"
        >
          Limpiar filtros
        </button>
      </div>
    </section>

    <!-- SECCIÓN: Destacados -->
    <section v-if="eventosFiltrados.length > 0" class="pt-4 border-t border-gray-100">
      <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight mb-6 flex items-center gap-2">
        <span></span> Eventos Destacados
      </h2>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <EventCard 
          v-for="evento in eventosFiltrados.slice(0, 4)" 
          :key="'destacado-' + evento.id" 
          :evento="evento"
          class="h-full" 
        />
      </div>
    </section>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import EventCard from '../Components/EventCard.vue';

const eventos = ref([]);
const busqueda = ref('');
const categoriaSeleccionada = ref('');
const mostrarTodosGrid = ref(false); // Estado para alternar entre carrusel y cuadrícula
const carruselRef = ref(null);

// Cargar los eventos desde la API de Laravel
const cargarEventos = async () => {
  try {
    const response = await fetch('/api/eventos');
    if (response.ok) {
      eventos.value = await response.json();
    }
  } catch (error) {
    console.error('Error al obtener los eventos:', error);
  }
};

// Control para desplazar el carrusel con las flechas
const scrollCarrusel = (direccion) => {
  if (!carruselRef.value) return;
  const desplazamiento = carruselRef.value.clientWidth;
  carruselRef.value.scrollBy({
    left: direccion === 'derecha' ? desplazamiento : -desplazamiento,
    behavior: 'smooth'
  });
};

// Obtener categorías únicas presentes en la BD
const categoriasDisponibles = computed(() => {
  const cats = eventos.value.map(e => e.categoria).filter(Boolean);
  return [...new Set(cats)];
});

// Filtrado reactivo al instante
const eventosFiltrados = computed(() => {
  return eventos.value.filter(evento => {
    const query = busqueda.value.toLowerCase().trim();
    const coincideTexto = 
      !query ||
      evento.titulo?.toLowerCase().includes(query) ||
      evento.descripcion?.toLowerCase().includes(query) ||
      evento.teatro_nombre?.toLowerCase().includes(query);

    const coincideCategoria = 
      !categoriaSeleccionada.value || 
      evento.categoria === categoriaSeleccionada.value;

    return coincideTexto && coincideCategoria;
  });
});

const limpiarFiltros = () => {
  busqueda.value = '';
  categoriaSeleccionada.value = '';
};

onMounted(() => {
  cargarEventos();
});
</script>

<style scoped>
/* Ocultar barra de scroll visible conservando el desplazamiento táctil/horizontal */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>