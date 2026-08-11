<template>
  <div>
    <!-- BARRA DE BÚSQUEDA Y FILTRO -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
      <div class="flex-1 flex items-center bg-white p-1.5 pl-4 rounded-full border border-gray-200 shadow-sm focus-within:ring-2 focus-within:ring-indigo-600 focus-within:border-transparent transition-all">
        <span class="text-gray-400 mr-2 text-base select-none">🔍</span>
        <input
          v-model="busqueda"
          type="text"
          placeholder="Buscar por correo..."
          class="w-full bg-transparent border-none text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0"
        />
      </div>

      <select
        v-model="filtroCategoria"
        class="px-4 py-2.5 bg-white border border-gray-200 rounded-full text-sm font-semibold text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 shrink-0"
      >
        <option value="">Todos los eventos</option>
        <option v-for="(info, clave) in CATEGORIAS" :key="clave" :value="clave">{{ info.label }}</option>
      </select>

      <button
        @click="cargarLogs"
        class="px-4 py-2.5 bg-indigo-50 text-indigo-600 font-semibold rounded-full text-sm hover:bg-indigo-100 transition-all shrink-0 flex items-center gap-1.5 justify-center"
      >
        🔄 Recargar
      </button>
    </div>

    <div v-if="cargando" class="text-center py-12 text-gray-500 font-medium">
      Cargando bitácora de auditoría...
    </div>

    <div v-else-if="error" class="text-center py-12 bg-red-50 text-red-600 text-sm rounded-2xl border border-red-100">
      {{ error }}
    </div>

    <div v-else class="bg-white shadow-xl rounded-3xl overflow-hidden border border-gray-100">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase">
              <th class="py-4 px-6">Fecha / Hora</th>
              <th class="py-4 px-6">Evento</th>
              <th class="py-4 px-6">Usuario</th>
              <th class="py-4 px-6">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm">
            <tr v-for="(entrada, idx) in entradasFiltradas" :key="idx" class="hover:bg-gray-50/50 transition-colors">
              <td class="py-3.5 px-6 text-xs text-gray-600 whitespace-nowrap">{{ formatearFecha(entrada.timestamp) }}</td>
              <td class="py-3.5 px-6">
                <span :class="claseBadge(entrada.category)">
                  {{ etiquetaCategoria(entrada.category) }}
                </span>
                <p class="text-[11px] text-gray-500 mt-1">{{ entrada.event_name }}</p>
              </td>
              <td class="py-3.5 px-6">
                <p class="font-medium text-gray-800">{{ entrada.correo || 'Desconocido' }}</p>
                <p v-if="entrada.usuario_id" class="text-[11px] text-gray-400 font-mono">ID #{{ entrada.usuario_id }}</p>
              </td>
              <td class="py-3.5 px-6 font-mono text-xs text-gray-500">{{ entrada.ip || '—' }}</td>
            </tr>
            <tr v-if="entradasFiltradas.length === 0">
              <td colspan="4" class="py-12 text-center text-gray-400">
                <p class="text-sm font-medium">No se encontraron registros con ese criterio.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { axios } from '../composables/useAuth';

const entradas = ref([]);
const cargando = ref(true);
const error = ref('');
const busqueda = ref('');
const filtroCategoria = ref('');

// Debe reflejar EXACTAMENTE las categorías que arma
// AdminController::getLogsAuditoria() (CATEGORIAS_AUDITORIA) — si el backend
// agrega una categoría nueva y esta lista no se actualiza, esas entradas caen
// en el badge 'otro' (gris) en vez de perderse silenciosamente.
const CATEGORIAS = {
  autenticacion:          { label: 'Autenticación',         clase: 'bg-blue-100 text-blue-700' },
  cambio_password:        { label: 'Cambio Contraseña',     clase: 'bg-orange-100 text-orange-700' },
  solicitud_organizador:  { label: 'Solicitud Organizador', clase: 'bg-indigo-100 text-indigo-700' },
  gestion_recinto:        { label: 'Gestión Recinto',       clase: 'bg-purple-100 text-purple-700' },
  gestion_evento:         { label: 'Gestión Evento',        clase: 'bg-purple-100 text-purple-700' },
  apartado_boleto:        { label: 'Apartado Boleto',       clase: 'bg-yellow-100 text-yellow-700' },
  compra_boleto:          { label: 'Compra Boleto',         clase: 'bg-green-100 text-green-700' },
  seguridad:              { label: 'Seguridad',             clase: 'bg-red-100 text-red-700' },
  otro:                   { label: 'Otro',                  clase: 'bg-gray-100 text-gray-600' },
};

const etiquetaCategoria = (categoria) => CATEGORIAS[categoria]?.label || CATEGORIAS.otro.label;
const claseBadge = (categoria) =>
  `inline-block px-3 py-1 text-xs font-bold rounded-full uppercase ${CATEGORIAS[categoria]?.clase || CATEGORIAS.otro.clase}`;

const formatearFecha = (timestamp) => {
  if (!timestamp) return '—';
  // El backend manda "YYYY-MM-DD HH:mm:ss" (formato de Monolog) — se le agrega
  // una "T" para que el parseo de Date sea consistente entre navegadores.
  const fecha = new Date(timestamp.replace(' ', 'T'));
  if (isNaN(fecha.getTime())) return timestamp;
  return fecha.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
};

const entradasFiltradas = computed(() => {
  const query = busqueda.value.toLowerCase().trim();
  return entradas.value.filter((e) => {
    const coincideTexto = !query || e.correo?.toLowerCase().includes(query);
    const coincideCategoria = !filtroCategoria.value || e.category === filtroCategoria.value;
    return coincideTexto && coincideCategoria;
  });
});

const cargarLogs = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const res = await axios.get('/api/admin/logs/auditoria');
    entradas.value = res.data;
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo cargar la bitácora de auditoría.';
  } finally {
    cargando.value = false;
  }
};

onMounted(cargarLogs);
</script>
