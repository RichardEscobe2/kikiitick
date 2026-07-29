<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Encabezado del Panel -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <span>🎪</span> Panel de Organizador
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Gestiona tus instalaciones físicas, distribución de aforo, pasillos y eventos.
          </p>
        </div>

        <div class="flex bg-gray-100 p-1 rounded-xl self-start md:self-auto">
          <button 
            @click="tabActual = 'recintos'"
            :class="['px-4 py-2 text-sm font-semibold rounded-lg transition-all', tabActual === 'recintos' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900']"
          >
            🏛️ Mis Recintos
          </button>
          <button 
            @click="tabActual = 'eventos'"
            :class="['px-4 py-2 text-sm font-semibold rounded-lg transition-all', tabActual === 'eventos' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900']"
          >
            🎟️ Mis Eventos
          </button>
        </div>
      </div>
    </div>

    <!-- Mensajes Globales -->
    <div v-if="mensajeExito" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex justify-between items-center text-sm">
      <span>{{ mensajeExito }}</span>
      <button @click="mensajeExito = ''" class="font-bold text-emerald-800">✕</button>
    </div>
    <div v-if="mensajeError" class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl flex justify-between items-center text-sm">
      <span>{{ mensajeError }}</span>
      <button @click="mensajeError = ''" class="font-bold text-rose-800">✕</button>
    </div>

    <!-- PESTAÑA 1: RECINTOS -->
    <div v-if="tabActual === 'recintos'">
      <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
        <div class="relative flex-1 max-w-md">
          <input 
            v-model="filtroBusqueda"
            type="text" 
            placeholder="Buscar recinto por nombre o ubicación..."
            class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
          />
          <span class="absolute left-3.5 top-3 text-gray-400 text-sm">🔍</span>
        </div>

        <button 
          @click="abrirModalRecinto()"
          class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all flex items-center justify-center gap-2"
        >
          <span>➕</span> Nuevo Recinto
        </button>
      </div>

      <div v-if="cargando" class="text-center py-16 text-gray-500 text-sm">
        Cargando tus recintos...
      </div>

      <div v-else-if="recintosFiltrados.length === 0" class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <div class="text-4xl mb-3">🏛️</div>
        <h3 class="text-base font-semibold text-gray-800">No se encontraron recintos</h3>
        <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
          Comienza registrando tu primer recinto para parametrizar zonas y crear eventos.
        </p>
        <button 
          @click="abrirModalRecinto()"
          class="mt-4 px-4 py-2 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-xl hover:bg-indigo-100 transition-all"
        >
          Registrar Recinto
        </button>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div 
          v-for="recinto in recintosFiltrados" 
          :key="recinto.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all p-6 flex flex-col justify-between"
        >
          <div>
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-bold text-gray-900">{{ recinto.nombre }}</h2>
                <span class="inline-block mt-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-bold rounded uppercase">
                  🏟️ TEATRO / ARENA
                </span>
              </div>
              <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-lg shrink-0">
                👥 Capacidad: {{ recinto.capacidad_total.toLocaleString() }}
              </span>
            </div>

            <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
              <span>📍</span> {{ recinto.ubicacion }}
            </p>

            <div class="mt-5">
              <div class="flex justify-between text-xs font-medium text-gray-600 mb-1.5">
                <span>Aforo Asignado en Zonas</span>
                <span>{{ obtenerSumaAsientos(recinto) }} / {{ recinto.capacidad_total }} ({{ calcularPorcentaje(recinto) }}%)</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                <div 
                  class="h-2.5 rounded-full transition-all duration-300"
                  :class="calcularPorcentaje(recinto) > 100 ? 'bg-rose-500' : 'bg-indigo-600'"
                  :style="{ width: Math.min(calcularPorcentaje(recinto), 100) + '%' }"
                ></div>
              </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
              <span class="text-xs font-semibold text-gray-500 block mb-2">
                Zonas Configuradas ({{ recinto.zonas ? recinto.zonas.length : 0 }}):
              </span>
              <div v-if="recinto.zonas && recinto.zonas.length > 0" class="flex flex-wrap gap-1.5">
                <span 
                  v-for="zona in recinto.zonas" 
                  :key="zona.id"
                  class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg"
                >
                  <strong class="font-semibold">{{ zona.nombre_zona }}</strong>
                  <span class="text-indigo-600 font-medium">({{ zona.nivel_proximidad }})</span>
                  <span v-if="zona.fila_inicio" class="text-gray-500 font-mono font-bold">[{{ zona.fila_inicio }}-{{ zona.fila_fin }}]</span>
                  <span class="text-gray-400">• {{ zona.capacidad_asientos }} as.</span>
                </span>
              </div>
              <p v-else class="text-xs text-gray-400 italic">No hay zonas asociadas. Haz clic en "Gestionar Zonas" para agregar.</p>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between gap-2">
            <button 
              @click="abrirModalZonas(recinto)"
              class="px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5"
            >
              ⚙️ Gestionar Zonas por Rangos
            </button>
            <div class="flex items-center gap-1">
              <button 
                @click="abrirModalRecinto(recinto)"
                class="px-3 py-1.5 text-gray-600 hover:bg-gray-100 text-xs font-medium rounded-lg transition-all"
              >
                ✏️ Editar
              </button>
              <button 
                @click="eliminarRecinto(recinto.id)"
                class="px-3 py-1.5 text-rose-600 hover:bg-rose-50 text-xs font-medium rounded-lg transition-all"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PESTAÑA 2: EVENTOS -->
    <div v-if="tabActual === 'eventos'">
      <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
        <div class="relative flex-1 max-w-md">
          <input 
            v-model="filtroBusquedaEvento"
            type="text" 
            placeholder="Buscar evento por título o categoría..."
            class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
          />
          <span class="absolute left-3.5 top-3 text-gray-400 text-sm">🔍</span>
        </div>

        <button 
          @click="abrirModalEvento()"
          class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all flex items-center justify-center gap-2"
        >
          <span>🎟️</span> Nuevo Evento
        </button>
      </div>

      <div v-if="cargandoEventos" class="text-center py-16 text-gray-500 text-sm">
        Cargando tus eventos...
      </div>

      <div v-else-if="eventosFiltrados.length === 0" class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
        <div class="text-4xl mb-3">🎟️</div>
        <h3 class="text-base font-semibold text-gray-800">No hay eventos creados</h3>
        <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
          Crea tu primer evento asociándolo a uno de tus recintos registrados.
        </p>
        <button 
          @click="abrirModalEvento()"
          class="mt-4 px-4 py-2 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-xl hover:bg-indigo-100 transition-all"
        >
          Crear Evento
        </button>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="evento in eventosFiltrados" 
          :key="evento.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col justify-between"
        >
          <div>
            <div class="h-40 w-full bg-gray-100 relative">
              <img 
                :src="evento.imagen_url" 
                :alt="evento.titulo"
                class="w-full h-full object-cover"
                @error="e => e.target.src = 'https://via.placeholder.com/400x200?text=Evento+KikiiTick'"
              />
              <span 
                :class="[
                  'absolute top-3 right-3 px-2.5 py-1 text-xs font-bold rounded-lg uppercase tracking-wide border shadow-sm',
                  evento.estatus === 'activo' ? 'bg-emerald-500 text-white border-emerald-600' :
                  evento.estatus === 'borrador' ? 'bg-amber-500 text-white border-amber-600' : 'bg-gray-500 text-white border-gray-600'
                ]"
              >
                {{ evento.estatus }}
              </span>
            </div>

            <div class="p-5">
              <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider block mb-1">
                🏷️ {{ evento.categoria }}
              </span>
              <h3 class="text-base font-bold text-gray-900 leading-snug line-clamp-1">{{ evento.titulo }}</h3>
              <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ evento.descripcion || 'Sin descripción disponible.' }}</p>

              <div class="mt-4 pt-3 border-t border-gray-100 space-y-1.5 text-xs text-gray-600">
                <div class="flex items-center gap-1.5">
                  <span>🏛️</span> <span class="font-semibold text-gray-800">{{ evento.teatro ? evento.teatro.nombre : 'Recinto no asignado' }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span>📅</span> <span>{{ formatearFecha(evento.fecha_hora) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-2">
            <button 
              @click="abrirModalPrecios(evento)"
              class="px-3 py-1.5 text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg transition-all flex items-center gap-1"
            >
              💲 Tarifas
            </button>
            <div class="flex items-center gap-1">
              <button 
                @click="abrirModalEvento(evento)"
                class="px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
              >
                ✏️
              </button>
              <button 
                @click="eliminarEvento(evento.id)"
                class="px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition-all"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: REGISTRAR / EDITAR RECINTO -->
    <div v-if="modalRecintoVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-gray-100">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-gray-900">
            {{ modoEdicionRecinto ? '✏️ Editar Recinto' : '➕ Registrar Nuevo Recinto' }}
          </h3>
          <button @click="modalRecintoVisible = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="guardarRecinto" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre del Recinto *</label>
            <input 
              v-model="formRecinto.nombre" 
              @input="limpiarNombreRecinto"
              type="text" 
              required
              placeholder="Ej. Teatro De La Ciudad Texcoco"
              class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">Ubicación / Dirección *</label>
              <input 
                v-model="formRecinto.ubicacion" 
                type="text" 
                required
                placeholder="Ej. Av. Juárez Sur #200, Centro, Texcoco"
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Aforo Máximo *</label>
              <input 
                v-model.number="formRecinto.capacidad_total" 
                type="number" 
                min="1"
                required
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Total de Filas (A - Z) *</label>
              <input 
                v-model.number="formRecinto.filas_totales" 
                type="number" 
                min="1"
                max="26"
                required
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              />
              <p class="text-[10px] text-gray-400 mt-0.5">Ej: 15 filas = De la A a la O</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Sillas Reales por Fila *</label>
              <input 
                v-model.number="formRecinto.asientos_por_fila" 
                type="number" 
                min="1"
                required
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>
          </div>

          <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-100 space-y-3">
            <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">🗺️ Distribución de Escenario y Pasillos</h4>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-gray-700 mb-1">Posición del Escenario</label>
                <!-- En el Modal de Recintos -->
<select 
  v-model="formRecinto.posicion_escenario"
  class="w-full px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg text-xs"
>
  <option value="arriba">Arriba (Teatro / Foro Frontal)</option>
  <option value="centro">Centro (Ring / Arena 360°)</option>
</select>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-gray-700 mb-1">Columnas que son Pasillos</label>
                <input 
                  v-model="formRecinto.pasillos_slots_str" 
                  type="text" 
                  placeholder="Ej. 5, 15 (Separados por coma)"
                  class="w-full px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg text-xs"
                />
              </div>
            </div>
          </div>

          <!-- 🛡️ ALERTA DE EXCESO DE AFORO EN VIVO -->
          <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 flex justify-between items-center text-xs">
            <span class="text-gray-600 font-medium">Aforo Total Calculado:</span>
            <strong :class="[ 'text-sm font-bold', excedeAforoModal ? 'text-rose-600' : 'text-indigo-600' ]">
              {{ (formRecinto.filas_totales || 0) * (formRecinto.asientos_por_fila || 0) }} Sillas / {{ formRecinto.capacidad_total || 0 }} Aforo
            </strong>
          </div>

          <div v-if="excedeAforoModal" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-xs font-medium">
            ⚠️ No se puede guardar: Las sillas calculadas ({{ (formRecinto.filas_totales || 0) * (formRecinto.asientos_por_fila || 0) }}) superan el aforo máximo permitido del recinto ({{ formRecinto.capacidad_total || 0 }}).
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="modalRecintoVisible = false"
              class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-xl font-medium"
            >
              Cancelar
            </button>
            <button 
              type="submit" 
              :disabled="enviandoForm || excedeAforoModal"
              class="px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ enviandoForm ? 'Guardando...' : 'Guardar Recinto y Generar Plano' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: GESTIONAR ZONAS POR RANGOS DE FILAS -->
    <div v-if="modalZonasVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-3xl w-full p-6 shadow-xl border border-gray-100 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900">⚙️ Configurar Zonas por Rangos de Filas</h3>
            <p class="text-xs text-gray-500">{{ recintoSeleccionado.nombre }} (Aforo Total: {{ recintoSeleccionado.capacidad_total }} Sillas)</p>
          </div>
          <button @click="modalZonasVisible = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
        </div>

        <div class="bg-gray-50 p-4 rounded-xl mb-6 border border-gray-100">
          <div class="flex justify-between text-xs font-semibold text-gray-700 mb-2">
            <span>Aforo Máximo: {{ recintoSeleccionado.capacidad_total }}</span>
            <span>Asignado: {{ obtenerSumaAsientos(recintoSeleccionado) }}</span>
            <span class="text-indigo-600">Disponible: {{ capacidadDisponible }}</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
            <div 
              class="h-2.5 bg-indigo-600 rounded-full transition-all duration-300"
              :style="{ width: Math.min(calcularPorcentaje(recintoSeleccionado), 100) + '%' }"
            ></div>
          </div>
        </div>

        <div class="mb-6">
          <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Zonas Configuradas</h4>
          <div v-if="recintoSeleccionado.zonas && recintoSeleccionado.zonas.length > 0" class="border border-gray-100 rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs text-gray-600">
              <thead class="bg-gray-50 font-semibold text-gray-700 border-b border-gray-100">
                <tr>
                  <th class="p-3">Nombre Zona</th>
                  <th class="p-3">Proximidad</th>
                  <th class="p-3">Rango Filas</th>
                  <th class="p-3">Aforo Real</th>
                  <th class="p-3 text-right">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="zona in recintoSeleccionado.zonas" :key="zona.id">
                  <td class="p-3 font-semibold text-gray-900">{{ zona.nombre_zona }}</td>
                  <td class="p-3">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-bold rounded">
                      {{ zona.nivel_proximidad }}
                    </span>
                  </td>
                  <td class="p-3 font-mono font-bold text-indigo-600">
                    Fila {{ zona.fila_inicio }} ➔ Fila {{ zona.fila_fin }}
                  </td>
                  <td class="p-3 font-bold text-gray-800">{{ zona.capacidad_asientos }} asientos</td>
                  <td class="p-3 text-right">
                    <button 
                      @click="eliminarZona(zona.id)"
                      class="text-rose-600 hover:text-rose-800 font-medium"
                    >
                      🗑️ Eliminar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-xs text-gray-400 italic py-2">No se han registrado zonas aún.</p>
        </div>

        <form @submit.prevent="guardarZona" class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-4">
          <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">➕ Asignar Rango de Filas a Nueva Zona</h4>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Nombre Zona *</label>
              <input 
                v-model="formZona.nombre_zona" 
                @input="limpiarNombreZona"
                type="text" 
                required
                placeholder="Ej. VIP Frontal"
                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Nivel Proximidad *</label>
              <select 
                v-model="formZona.nivel_proximidad"
                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500/20"
              >
                <option value="0x">0x (Cortesías / Prensa)</option>
                <option value="1x">1x (Cercano / VIP)</option>
                <option value="2x">2x (Proximidad Media)</option>
                <option value="3x">3x (Lejano / General)</option>
                <option value="4x">4x (Muy Lejano / Vista Limitada)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 p-3 bg-white rounded-xl border border-indigo-100">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Fila Desde *</label>
              <select 
                v-model="formZona.fila_inicio"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-xs font-bold font-mono"
              >
                <option 
                  v-for="f in filasDisponiblesRecinto" 
                  :key="f" 
                  :value="f"
                  :disabled="filasAsignadasMap[f]"
                >
                  Fila {{ f }} {{ filasAsignadasMap[f] ? '(Asignada)' : ' (Libre)' }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Fila Hasta *</label>
              <select 
                v-model="formZona.fila_fin"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-xs font-bold font-mono"
              >
                <option 
                  v-for="f in filasDisponiblesRecinto" 
                  :key="f" 
                  :value="f"
                  :disabled="filasAsignadasMap[f] || f < formZona.fila_inicio"
                >
                  Fila {{ f }} {{ filasAsignadasMap[f] ? '(Asignada)' : '' }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="rangoTieneSolapamiento" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-xs font-medium">
            ⚠️ El rango seleccionado ({{ formZona.fila_inicio }} - {{ formZona.fila_fin }}) incluye filas que ya pertenecen a otra zona.
          </div>

          <div class="flex items-center justify-between pt-2">
            <span class="text-xs font-semibold text-gray-700">
              Aforo en Rango: <strong class="text-indigo-600 text-sm font-bold">{{ calculoAforoZonaRango }} Sillas Reales</strong>
            </span>

            <button 
              type="submit" 
              :disabled="enviandoZona || calculoAforoZonaRango <= 0 || rangoTieneSolapamiento"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50 transition-all"
            >
              {{ enviandoZona ? 'Asignando Filas...' : '➕ Asignar Filas a Zona' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EVENTO -->
    <div v-if="modalEventoVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-gray-100 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-gray-900">
            {{ modoEdicionEvento ? '✏️ Editar Evento' : '🎟️ Crear Nuevo Evento' }}
          </h3>
          <button @click="modalEventoVisible = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="guardarEvento" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Recinto / Teatro *</label>
            <select 
              v-model="formEvento.teatro_id" 
              required
              class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
            >
              <option value="" disabled>Selecciona un recinto...</option>
              <option v-for="r in recintos" :key="r.id" :value="r.id">
                {{ r.nombre }} (Aforo: {{ r.capacidad_total }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Título del Evento *</label>
            <input 
              v-model="formEvento.titulo" 
              type="text" 
              required
              placeholder="Ej. Concierto Sinfónico 2026"
              class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Categoría *</label>
              <select 
                v-model="formEvento.categoria" 
                required
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              >
                <option value="Concierto">Concierto</option>
                <option value="Teatro">Teatro</option>
                <option value="Conferencia">Conferencia</option>
                <option value="Deportes">Deportes</option>
                <option value="StandUp">Stand Up</option>
                <option value="Otro">Otro</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Estatus *</label>
              <select 
                v-model="formEvento.estatus" 
                required
                class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
              >
                <option value="borrador">Borrador</option>
                <option value="activo">Activo (Público)</option>
                <option value="finalizado">Finalizado</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha y Hora *</label>
            <input 
              v-model="formEvento.fecha_hora" 
              type="datetime-local" 
              required
              class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Imagen / Banner del Evento</label>
            <input 
              type="file" 
              accept="image/*"
              @change="alSeleccionarImagen"
              class="block w-full text-xs text-gray-500 border border-gray-300 rounded-xl cursor-pointer bg-gray-50 p-2 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700"
            />

            <div v-if="imagenPreview" class="mt-2 relative rounded-xl overflow-hidden border border-gray-200 h-32">
              <img :src="imagenPreview" class="w-full h-full object-cover" alt="Vista previa" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Descripción</label>
            <textarea 
              v-model="formEvento.descripcion" 
              rows="3"
              placeholder="Detalles sobre el evento..."
              class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3">
            <button 
              type="button" 
              @click="modalEventoVisible = false"
              class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-xl font-medium"
            >
              Cancelar
            </button>
            <button 
              type="submit" 
              :disabled="enviandoEvento"
              class="px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl disabled:opacity-50"
            >
              {{ enviandoEvento ? 'Guardando...' : 'Guardar Evento' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL TARIFAS -->
    <div v-if="modalPreciosVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-gray-100 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900">💲 Configurar Tarifas por Zona</h3>
            <p class="text-xs text-gray-500">{{ eventoPreciosSeleccionado?.titulo }}</p>
          </div>
          <button @click="modalPreciosVisible = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
        </div>

        <div v-if="cargandoPrecios" class="text-center py-8 text-gray-500 text-sm">
          Cargando tarifas...
        </div>

        <form v-else @submit.prevent="guardarPrecios" class="space-y-4">
          <div class="space-y-3 border-t border-b border-gray-100 py-3 my-2">
            <div 
              v-for="item in listaPreciosZona" 
              :key="item.zona_teatro_id"
              class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
              <div>
                <span class="font-bold text-gray-900 text-xs">{{ item.nombre_zona }}</span>
                <p class="text-[11px] text-gray-500 mt-0.5">
                  Capacidad: {{ item.capacidad_asientos }} asientos
                </p>
              </div>

              <div class="w-full sm:w-36">
                <label class="block text-[10px] font-bold text-gray-500 mb-1">Precio Boleto ($MXN)</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-xs text-gray-400 font-bold">$</span>
                  <input 
                    v-model.number="item.precio_base" 
                    type="number" 
                    step="0.01"
                    min="0"
                    required
                    class="w-full pl-7 pr-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-indigo-500/20"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="modalPreciosVisible = false"
              class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-xl font-medium"
            >
              Cancelar
            </button>
            <button 
              type="submit" 
              :disabled="enviandoPrecios"
              class="px-5 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl disabled:opacity-50"
            >
              {{ enviandoPrecios ? 'Guardando...' : '💾 Guardar Tarifas' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { axios } from '../composables/useAuth';

const tabActual = ref('recintos');
const recintos = ref([]);
const eventos = ref([]);
const cargando = ref(true);
const cargandoEventos = ref(false);
const filtroBusqueda = ref('');
const filtroBusquedaEvento = ref('');
const mensajeExito = ref('');
const mensajeError = ref('');

// Modales Recinto / Zonas
const modalRecintoVisible = ref(false);
const modoEdicionRecinto = ref(false);
const recintoIdEdicion = ref(null);
const enviandoForm = ref(false);

const modalZonasVisible = ref(false);
const recintoSeleccionado = ref(null);
const enviandoZona = ref(false);

// Modales Evento / Tarifas
const modalEventoVisible = ref(false);
const modoEdicionEvento = ref(false);
const eventoIdEdicion = ref(null);
const enviandoEvento = ref(false);
const archivoImagen = ref(null);
const imagenPreview = ref(null);

const modalPreciosVisible = ref(false);
const eventoPreciosSeleccionado = ref(null);
const listaPreciosZona = ref([]);
const cargandoPrecios = ref(false);
const enviandoPrecios = ref(false);

const formRecinto = ref({
  nombre: '',
  ubicacion: '',
  capacidad_total: 300,
  filas_totales: 15,
  asientos_por_fila: 20,
  pasillos_slots_str: '5, 15',
  posicion_escenario: 'arriba'
});

const formZona = ref({
  nombre_zona: '',
  nivel_proximidad: '1x',
  fila_inicio: 'A',
  fila_fin: 'A',
  es_numerada: true
});

const formEvento = ref({
  teatro_id: '',
  titulo: '',
  descripcion: '',
  imagen_url: '',
  categoria: 'Concierto',
  fecha_hora: '',
  estatus: 'activo'
});

const alSeleccionarImagen = (event) => {
  const file = event.target.files[0];
  if (file) {
    archivoImagen.value = file;
    imagenPreview.value = URL.createObjectURL(file);
  }
};

const limpiarNombreRecinto = () => {
  formRecinto.value.nombre = formRecinto.value.nombre.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
};

const limpiarNombreZona = () => {
  formZona.value.nombre_zona = formZona.value.nombre_zona.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
};

const excedeAforoModal = computed(() => {
  const cap = Number(formRecinto.value.capacidad_total || 0);
  const calculadas = (Number(formRecinto.value.filas_totales) || 0) * (Number(formRecinto.value.asientos_por_fila) || 0);
  return cap > 0 && calculadas > cap;
});

const recintosFiltrados = computed(() => {
  if (!filtroBusqueda.value) return recintos.value;
  const q = filtroBusqueda.value.toLowerCase();
  return recintos.value.filter(r => 
    r.nombre.toLowerCase().includes(q) || r.ubicacion.toLowerCase().includes(q)
  );
});

const eventosFiltrados = computed(() => {
  if (!filtroBusquedaEvento.value) return eventos.value;
  const q = filtroBusquedaEvento.value.toLowerCase();
  return eventos.value.filter(e => 
    e.titulo.toLowerCase().includes(q) || e.categoria.toLowerCase().includes(q)
  );
});

const capacidadDisponible = computed(() => {
  if (!recintoSeleccionado.value) return 0;
  const asignado = obtenerSumaAsientos(recintoSeleccionado.value);
  return Math.max(0, recintoSeleccionado.value.capacidad_total - asignado);
});

const obtenerSumaAsientos = (recinto) => {
  if (!recinto || !recinto.zonas) return 0;
  return recinto.zonas.reduce((acc, z) => acc + Number(z.capacidad_asientos), 0);
};

const calcularPorcentaje = (recinto) => {
  if (!recinto || !recinto.capacidad_total) return 0;
  const suma = obtenerSumaAsientos(recinto);
  return Math.round((suma / recinto.capacidad_total) * 100);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  const f = new Date(fechaStr);
  return f.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
};

const filasDisponiblesRecinto = computed(() => {
  if (!recintoSeleccionado.value || !recintoSeleccionado.value.filas_totales) return [];
  const total = recintoSeleccionado.value.filas_totales;
  const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
  return abecedario.slice(0, total);
});

const filasAsignadasMap = computed(() => {
  if (!recintoSeleccionado.value || !recintoSeleccionado.value.asientos) return {};
  const map = {};
  recintoSeleccionado.value.asientos.forEach(a => {
    if (a.zona_teatro_id) {
      map[a.fila] = true;
    }
  });
  return map;
});

const calculoAforoZonaRango = computed(() => {
  if (!recintoSeleccionado.value || !recintoSeleccionado.value.asientos) return 0;
  
  const fIni = formZona.value.fila_inicio;
  const fFin = formZona.value.fila_fin;
  if (!fIni || !fFin || fIni > fFin) return 0;

  const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
  const idxIni = abecedario.indexOf(fIni);
  const idxFin = abecedario.indexOf(fFin);
  
  if (idxIni === -1 || idxFin === -1) return 0;

  const rangoElegido = abecedario.slice(idxIni, idxFin + 1);

  return recintoSeleccionado.value.asientos.filter(
    a => rangoElegido.includes(a.fila) && a.tipo === 'asiento'
  ).length;
});

const rangoTieneSolapamiento = computed(() => {
  const fIni = formZona.value.fila_inicio;
  const fFin = formZona.value.fila_fin;
  if (!fIni || !fFin || fIni > fFin) return false;

  const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
  const idxIni = abecedario.indexOf(fIni);
  const idxFin = abecedario.indexOf(fFin);
  const rangoElegido = abecedario.slice(idxIni, idxFin + 1);

  return rangoElegido.some(f => filasAsignadasMap.value[f]);
});

const cargarTeatros = async () => {
  cargando.value = true;
  try {
    const res = await axios.get('/api/organizador/teatros');
    recintos.value = res.data;
  } catch (err) {
    mensajeError.value = 'Error de conexión al cargar recintos.';
  } finally {
    cargando.value = false;
  }
};

const cargarEventos = async () => {
  cargandoEventos.value = true;
  try {
    const res = await axios.get('/api/organizador/eventos');
    eventos.value = res.data;
  } catch (err) {
    mensajeError.value = 'Error de conexión al cargar eventos.';
  } finally {
    cargandoEventos.value = false;
  }
};

const abrirModalRecinto = (recinto = null) => {
  mensajeError.value = '';
  if (recinto) {
    modoEdicionRecinto.value = true;
    recintoIdEdicion.value = recinto.id;
    formRecinto.value = { 
      nombre: recinto.nombre, 
      ubicacion: recinto.ubicacion, 
      capacidad_total: recinto.capacidad_total || 300,
      filas_totales: recinto.filas_totales || 15,
      asientos_por_fila: recinto.asientos_por_fila || 20,
      pasillos_slots_str: recinto.pasillos_slots ? recinto.pasillos_slots.join(', ') : '',
      posicion_escenario: recinto.posicion_escenario || 'arriba'
    };
  } else {
    modoEdicionRecinto.value = false;
    recintoIdEdicion.value = null;
    formRecinto.value = { 
      nombre: '', 
      ubicacion: '', 
      capacidad_total: 300,
      filas_totales: 15,
      asientos_por_fila: 20,
      pasillos_slots_str: '5, 15',
      posicion_escenario: 'arriba'
    };
  }
  modalRecintoVisible.value = true;
};

const guardarRecinto = async () => {
  if (excedeAforoModal.value) return;
  enviandoForm.value = true;
  mensajeError.value = '';
  mensajeExito.value = '';

  const pasillosArr = formRecinto.value.pasillos_slots_str
    ? formRecinto.value.pasillos_slots_str.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n) && n > 0)
    : [];

  const payload = {
    ...formRecinto.value,
    pasillos_slots: pasillosArr
  };

  const url = modoEdicionRecinto.value ? `/api/organizador/teatros/${recintoIdEdicion.value}` : '/api/organizador/teatros';
  const method = modoEdicionRecinto.value ? 'put' : 'post';

  try {
    const res = await axios[method](url, payload);
    mensajeExito.value = res.data.message;
    modalRecintoVisible.value = false;
    await cargarTeatros();
  } catch (err) {
    const data = err.response?.data;
    mensajeError.value = data
      ? (data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Error al guardar.'))
      : 'Error de conexión.';
  } finally {
    enviandoForm.value = false;
  }
};

const eliminarRecinto = async (id) => {
  if (!confirm('¿Deseas eliminar este recinto?')) return;
  try {
    const res = await axios.delete(`/api/organizador/teatros/${id}`);
    mensajeExito.value = res.data.message;
    await cargarTeatros();
  } catch (err) {
    mensajeError.value = err.response?.data?.message || 'Error al eliminar.';
  }
};

const abrirModalZonas = (recinto) => {
  recintoSeleccionado.value = recinto;
  
  const filas = filasDisponiblesRecinto.value;
  const primeraLibre = filas.find(f => !filasAsignadasMap.value[f]) || filas[0] || 'A';

  formZona.value = { 
    nombre_zona: '', 
    nivel_proximidad: '1x', 
    fila_inicio: primeraLibre,
    fila_fin: primeraLibre,
    es_numerada: true
  };
  modalZonasVisible.value = true;
};

const guardarZona = async () => {
  enviandoZona.value = true;
  mensajeError.value = '';

  try {
    const res = await axios.post(`/api/organizador/teatros/${recintoSeleccionado.value.id}/zonas`, formZona.value);
    const data = res.data;
    recintoSeleccionado.value = data.teatro;
    const idx = recintos.value.findIndex(r => r.id === data.teatro.id);
    if (idx !== -1) recintos.value[idx] = data.teatro;

    const filas = filasDisponiblesRecinto.value;
    const primeraLibre = filas.find(f => !filasAsignadasMap.value[f]) || filas[0] || 'A';
    formZona.value.nombre_zona = '';
    formZona.value.fila_inicio = primeraLibre;
    formZona.value.fila_fin = primeraLibre;
  } catch (err) {
    const data = err.response?.data;
    mensajeError.value = data
      ? (data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Error al guardar zona.'))
      : 'Error al crear zona.';
  } finally {
    enviandoZona.value = false;
  }
};

const eliminarZona = async (zonaId) => {
  if (!confirm('¿Eliminar esta zona y liberar sus filas asignadas?')) return;
  try {
    const res = await axios.delete(`/api/organizador/zonas/${zonaId}`);
    const data = res.data;
    recintoSeleccionado.value = data.teatro;
    const idx = recintos.value.findIndex(r => r.id === data.teatro.id);
    if (idx !== -1) recintos.value[idx] = data.teatro;
  } catch (err) {
    mensajeError.value = err.response?.data?.message || 'Error al eliminar zona.';
  }
};

const abrirModalEvento = (evento = null) => {
  mensajeError.value = '';
  archivoImagen.value = null;

  if (recintos.value.length === 0) {
    mensajeError.value = 'Debes tener al menos un recinto creado antes de registrar un evento.';
    return;
  }

  if (evento) {
    modoEdicionEvento.value = true;
    eventoIdEdicion.value = evento.id;
    const fechaFormatted = evento.fecha_hora ? new Date(evento.fecha_hora).toISOString().slice(0, 16) : '';
    formEvento.value = {
      teatro_id: evento.teatro_id,
      titulo: evento.titulo,
      descripcion: evento.descripcion || '',
      imagen_url: evento.imagen_url || '',
      categoria: evento.categoria,
      fecha_hora: fechaFormatted,
      estatus: evento.estatus
    };
    imagenPreview.value = evento.imagen_url || null;
  } else {
    modoEdicionEvento.value = false;
    eventoIdEdicion.value = null;
    formEvento.value = {
      teatro_id: recintos.value[0]?.id || '',
      titulo: '',
      descripcion: '',
      imagen_url: '',
      categoria: 'Concierto',
      fecha_hora: '',
      estatus: 'activo'
    };
    imagenPreview.value = null;
  }
  modalEventoVisible.value = true;
};

const guardarEvento = async () => {
  enviandoEvento.value = true;
  mensajeError.value = '';
  mensajeExito.value = '';

  const url = modoEdicionEvento.value ? `/api/organizador/eventos/${eventoIdEdicion.value}` : '/api/organizador/eventos';
  const formData = new FormData();
  formData.append('teatro_id', formEvento.value.teatro_id);
  formData.append('titulo', formEvento.value.titulo);
  formData.append('categoria', formEvento.value.categoria);
  formData.append('estatus', formEvento.value.estatus);
  formData.append('fecha_hora', formEvento.value.fecha_hora);
  formData.append('descripcion', formEvento.value.descripcion || '');

  if (modoEdicionEvento.value) formData.append('_method', 'PUT');
  if (archivoImagen.value) formData.append('imagen', archivoImagen.value);
  else if (formEvento.value.imagen_url) formData.append('imagen_url', formEvento.value.imagen_url);

  try {
    const res = await axios.post(url, formData);
    mensajeExito.value = res.data.message;
    modalEventoVisible.value = false;
    archivoImagen.value = null;
    imagenPreview.value = null;
    await cargarEventos();
  } catch (err) {
    const data = err.response?.data;
    mensajeError.value = data
      ? (data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Error al guardar evento.'))
      : 'Error de conexión con el servidor.';
  } finally {
    enviandoEvento.value = false;
  }
};

const eliminarEvento = async (id) => {
  if (!confirm('¿Estás seguro de eliminar este evento?')) return;
  try {
    const res = await axios.delete(`/api/organizador/eventos/${id}`);
    mensajeExito.value = res.data.message;
    await cargarEventos();
  } catch (err) {
    mensajeError.value = err.response?.data?.message || 'Error al eliminar evento.';
  }
};

const abrirModalPrecios = async (evento) => {
  mensajeError.value = '';
  eventoPreciosSeleccionado.value = evento;
  cargandoPrecios.value = true;
  modalPreciosVisible.value = true;

  try {
    const res = await axios.get(`/api/organizador/eventos/${evento.id}/precios`);
    listaPreciosZona.value = res.data.precios;
  } catch (err) {
    mensajeError.value = err.response?.data?.message || (err.response ? 'Error al cargar las tarifas del evento.' : 'Error de conexión al cargar tarifas.');
    modalPreciosVisible.value = false;
  } finally {
    cargandoPrecios.value = false;
  }
};

const guardarPrecios = async () => {
  enviandoPrecios.value = true;
  mensajeError.value = '';
  mensajeExito.value = '';

  try {
    const payload = {
      precios: listaPreciosZona.value.map(item => ({
        zona_teatro_id: item.zona_teatro_id,
        precio_base: item.precio_base
      }))
    };

    const res = await axios.post(`/api/organizador/eventos/${eventoPreciosSeleccionado.value.id}/precios`, payload);
    mensajeExito.value = res.data.message;
    modalPreciosVisible.value = false;
    await cargarEventos();
  } catch (err) {
    const data = err.response?.data;
    mensajeError.value = data
      ? (data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Error al guardar tarifas.'))
      : 'Error de conexión al guardar tarifas.';
  } finally {
    enviandoPrecios.value = false;
  }
};

onMounted(async () => {
  await cargarTeatros();
  await cargarEventos();
});

watch(tabActual, (nuevaTab) => {
  if (nuevaTab === 'eventos') cargarEventos();
});
</script>