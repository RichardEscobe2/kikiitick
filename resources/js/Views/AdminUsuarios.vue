<template>
  <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
    <!-- Encabezado -->
    <div class="flex justify-between items-center mb-2">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
        <p class="text-sm text-gray-500">Administra privilegios, contraseñas y solicitudes de organizadores</p>
      </div>
      <button 
        @click="recargarTodo" 
        class="px-4 py-2 bg-indigo-50 text-indigo-600 font-semibold rounded-xl text-sm hover:bg-indigo-100 transition-all flex items-center gap-1.5"
      >
        🔄 Recargar
      </button>
    </div>

    <!-- Pestañas de Navegación -->
    <div class="flex gap-2 border-b border-gray-200 pb-3">
      <button 
        @click="pestañaActiva = 'usuarios'"
        :class="[
          'px-4 py-2 text-sm font-bold rounded-xl transition-all flex items-center gap-2',
          pestañaActiva === 'usuarios' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
        ]"
      >
        👥 Todos los Usuarios
      </button>
      <button 
        @click="pestañaActiva = 'solicitudes'"
        :class="[
          'px-4 py-2 text-sm font-bold rounded-xl transition-all flex items-center gap-2 relative',
          pestañaActiva === 'solicitudes' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
        ]"
      >
        📋 Solicitudes de Organizador
        <span
          v-if="solicitudes.length > 0"
          class="px-2 py-0.5 text-xs bg-amber-500 text-white font-black rounded-full animate-pulse"
        >
          {{ solicitudes.length }}
        </span>
      </button>
      <button
        @click="pestañaActiva = 'logs'"
        :class="[
          'px-4 py-2 text-sm font-bold rounded-xl transition-all flex items-center gap-2',
          pestañaActiva === 'logs' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
        ]"
      >
        🛡️ Auditoría
      </button>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTROS (ESTILO HOME) — la pestaña de Auditoría
         trae su propia barra de búsqueda/filtro (AdminLogs.vue), así que esta
         se oculta ahí para no duplicar UI ni mezclar el ref `busqueda`
         compartido con la búsqueda de correos de la bitácora. -->
    <section v-if="pestañaActiva !== 'logs'" class="max-w-xl mx-auto my-2">
      <div class="flex items-center bg-white p-1.5 pl-4 rounded-full border border-gray-200 shadow-sm focus-within:ring-2 focus-within:ring-indigo-600 focus-within:border-transparent transition-all">
        <!-- Icono de Lupa -->
        <span class="text-gray-400 mr-2 text-base select-none">🔍</span>

        <!-- Input de Búsqueda -->
        <input 
          v-model="busqueda"
          type="text" 
          :placeholder="pestañaActiva === 'usuarios' ? 'Buscar usuario por nombre, correo o ID...' : 'Buscar solicitud por solicitante, correo o recinto...'" 
          class="w-full bg-transparent border-none text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0 pr-2"
        />

        <!-- Botón de Filtros por Rol/Cargo (Solo activo en pestaña Usuarios) -->
        <div v-if="pestañaActiva === 'usuarios'" class="relative flex items-center shrink-0">
          <select 
            v-model="rolSeleccionado"
            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
            title="Filtrar por rol / cargo"
          >
            <option value="">Todos los roles</option>
            <option value="cliente">Cliente</option>
            <option value="organizador">Organizador</option>
            <option value="admin">Administrador</option>
          </select>

          <button 
            type="button"
            :class="rolSeleccionado ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
            class="p-2.5 rounded-full transition-colors flex items-center justify-center"
            title="Filtrar por rol"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Indicador de filtro activo opcional -->
      <div v-if="rolSeleccionado && pestañaActiva === 'usuarios'" class="text-center mt-2">
        <span class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full font-medium">
          Filtrado por rol: <strong class="capitalize">{{ rolSeleccionado }}</strong>
          <button @click="rolSeleccionado = ''" class="hover:text-indigo-900 font-bold ml-1">✕</button>
        </span>
      </div>
    </section>

    <!-- Mensaje de Notificación General -->
    <div v-if="mensaje" class="mb-4 p-4 bg-emerald-50 text-emerald-700 text-sm rounded-xl border border-emerald-200 flex justify-between items-center">
      <span>{{ mensaje }}</span>
      <button @click="mensaje = ''" class="text-emerald-500 hover:text-emerald-800 font-bold">✕</button>
    </div>
    <div v-if="errorGeneral" class="mb-4 p-4 bg-red-50 text-red-700 text-sm rounded-xl border border-red-200 flex justify-between items-center">
      <span>{{ errorGeneral }}</span>
      <button @click="errorGeneral = ''" class="text-red-500 hover:text-red-800 font-bold">✕</button>
    </div>

    <!-- PESTAÑA 1: TABLA DE USUARIOS -->
    <div v-if="pestañaActiva === 'usuarios'">
      <!-- Indicador de Carga -->
      <div v-if="cargando" class="text-center py-12 text-gray-500 font-medium">
        Cargando usuarios desde la base de datos...
      </div>

      <!-- Tabla de Usuarios -->
      <div v-else class="bg-white shadow-xl rounded-3xl overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase">
                <th class="py-4 px-6">ID</th>
                <th class="py-4 px-6">Nombre</th>
                <th class="py-4 px-6">Correo Electrónico</th>
                <th class="py-4 px-6">Rol</th>
                <th class="py-4 px-6">Cambiar Rol</th>
                <th class="py-4 px-6 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
              <tr 
                v-for="usuario in usuariosFiltrados" 
                :key="usuario.id" 
                class="hover:bg-gray-50/50 transition-colors"
              >
                <td class="py-4 px-6 font-bold text-gray-700">#{{ usuario.id }}</td>
                <td class="py-4 px-6 font-medium text-gray-900">{{ usuario.nombre }}</td>
                <td class="py-4 px-6 text-gray-600 font-mono text-xs">{{ usuario.correo }}</td>
                
                <!-- BADGE ROL -->
                <td class="py-4 px-6">
                  <span 
                    :class="[
                      'px-3 py-1 text-xs font-bold rounded-full uppercase',
                      usuario.rol === 'admin' ? 'bg-purple-100 text-purple-700' : '',
                      usuario.rol === 'organizador' ? 'bg-blue-100 text-blue-700' : '',
                      (usuario.rol === 'cliente' || usuario.rol === 'comprador' || !usuario.rol) ? 'bg-emerald-100 text-emerald-700' : ''
                    ]"
                  >
                    {{ usuario.rol === 'comprador' ? 'cliente' : (usuario.rol || 'cliente') }}
                  </span>
                </td>

                <!-- SELECTOR DE ROL -->
                <td class="py-4 px-6">
                  <select 
                    :value="usuario.rol === 'comprador' ? 'cliente' : (usuario.rol || 'cliente')"
                    @change="actualizarRol(usuario.id, $event.target.value)"
                    class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-600 transition-all"
                  >
                    <option value="cliente">Cliente</option>
                    <option value="organizador">Organizador</option>
                    <option value="admin">Administrador</option>
                  </select>
                </td>

                <!-- COLUMNA DE ACCIONES -->
                <td class="py-4 px-6 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <!-- Botón Cambiar Contraseña -->
                    <button 
                      @click="abrirModalPassword(usuario)"
                      class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                      title="Cambiar Contraseña"
                    >
                      🔑 Clave
                    </button>

                    <!-- Botón Eliminar — deshabilitado (no oculto, para que quede
                         claro que la protección es intencional, no un bug) sobre
                         cuentas admin o la propia cuenta del admin autenticado. -->
                    <button
                      v-if="usuario.rol !== 'admin' && usuario.id !== usuarioActual?.id"
                      @click="eliminarUsuario(usuario.id, usuario.correo)"
                      class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition-all flex items-center gap-1 cursor-pointer"
                      title="Borrado lógico"
                    >
                      🗑️ Borrar
                    </button>
                    <button
                      v-else
                      disabled
                      class="px-3 py-1.5 bg-gray-100 text-gray-400 rounded-lg text-xs font-bold cursor-not-allowed flex items-center gap-1"
                      :title="usuario.id === usuarioActual?.id
                        ? 'No puedes eliminar tu propia cuenta de administrador.'
                        : 'Las cuentas de administrador son protegidas y no pueden ser eliminadas.'"
                    >
                      🗑️ Borrar
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="usuariosFiltrados.length === 0">
                <td colspan="6" class="py-12 text-center text-gray-400">
                  <p class="text-base font-medium">No se encontraron usuarios con ese criterio de búsqueda.</p>
                  <button 
                    v-if="busqueda || rolSeleccionado" 
                    @click="limpiarFiltros" 
                    class="mt-2 text-xs text-indigo-600 font-bold hover:underline"
                  >
                    Limpiar filtros de búsqueda
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PESTAÑA 2: TABLA DE SOLICITUDES DE ORGANIZADOR -->
    <div v-else-if="pestañaActiva === 'solicitudes'">
      <div v-if="cargandoSolicitudes" class="text-center py-12 text-gray-500 font-medium">
        Cargando solicitudes de organizador...
      </div>

      <div v-else class="bg-white shadow-xl rounded-3xl overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase">
                <th class="py-4 px-6">Solicitante</th>
                <th class="py-4 px-6">Contacto</th>
                <th class="py-4 px-6">Recinto Propuesto</th>
                <th class="py-4 px-6">Ubicación / Capacidad</th>
                <th class="py-4 px-6 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
              <tr
                v-for="solicitud in solicitudesFiltradas"
                :key="solicitud.id"
                class="hover:bg-gray-50/50 transition-colors"
              >
                <!-- SOLICITANTE -->
                <td class="py-4 px-6">
                  <p class="font-bold text-gray-900">{{ solicitud.nombre }}</p>
                  <p class="text-xs text-gray-500 font-mono">{{ solicitud.correo }}</p>
                </td>

                <!-- CONTACTO -->
                <td class="py-4 px-6 text-xs text-gray-600">
                  <template v-if="datosRecinto(solicitud)">
                    <p v-if="datosRecinto(solicitud).telefono"><strong>Tel:</strong> {{ datosRecinto(solicitud).telefono }}</p>
                    <p v-if="datosRecinto(solicitud).rfc"><strong>RFC:</strong> {{ datosRecinto(solicitud).rfc }}</p>
                  </template>
                  <span v-if="!datosRecinto(solicitud)?.telefono && !datosRecinto(solicitud)?.rfc" class="text-gray-400 italic">No especificado</span>
                </td>

                <!-- RECINTO -->
                <td class="py-4 px-6">
                  <div v-if="datosRecinto(solicitud)">
                    <p class="font-bold text-indigo-700">{{ datosRecinto(solicitud).nombre }}</p>
                  </div>
                  <span v-else class="text-xs text-gray-400 italic">Sin recinto registrado</span>
                </td>

                <!-- UBICACIÓN / CAPACIDAD -->
                <td class="py-4 px-6 text-xs text-gray-600">
                  <div v-if="datosRecinto(solicitud)">
                    <p>📍 {{ datosRecinto(solicitud).direccion || 'Sin dirección' }}</p>
                    <p class="font-semibold text-gray-800">👥 Capacidad: {{ datosRecinto(solicitud).capacidad || 'N/A' }} personas</p>
                  </div>
                  <span v-else class="text-gray-400 italic">N/A</span>
                </td>

                <!-- ACCIONES -->
                <td class="py-4 px-6 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="abrirDetalleSolicitud(solicitud)"
                      class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                      title="Ver detalle completo"
                    >
                      👁️ Detalle
                    </button>
                    <button
                      @click="aprobarOrganizador(solicitud.id)"
                      :disabled="procesandoAccion === solicitud.id"
                      class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-bold transition-all flex items-center gap-1 disabled:opacity-50"
                      title="Aprobar Solicitud"
                    >
                      ✅ Aprobar
                    </button>
                    <button
                      @click="rechazarOrganizador(solicitud.id)"
                      :disabled="procesandoAccion === solicitud.id"
                      class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition-all flex items-center gap-1 disabled:opacity-50"
                      title="Rechazar Solicitud"
                    >
                      ❌ Rechazar
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="solicitudesFiltradas.length === 0">
                <td colspan="5" class="py-8 text-center text-gray-400">
                  <p class="text-sm font-medium">No se encontraron solicitudes con esa búsqueda.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PESTAÑA 3: BITÁCORA DE AUDITORÍA -->
    <div v-else-if="pestañaActiva === 'logs'">
      <AdminLogs />
    </div>

    <!-- ========================================== -->
    <!-- MODAL PARA CAMBIAR CONTRASEÑA              -->
    <!-- ========================================== -->
    <div 
      v-if="modalAbierto" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 transition-all"
    >
      <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 border border-gray-100 animate-fadeIn">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-gray-900">Cambiar Contraseña</h3>
          <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600 font-bold text-xl">✕</button>
        </div>

        <p class="text-xs text-gray-500 mb-4">
          Actualizando credenciales para: <strong class="text-gray-800">{{ usuarioSeleccionado?.correo }}</strong>
        </p>

        <!-- Mensaje de Error en el Modal -->
        <div v-if="errorModal" class="mb-4 p-3 bg-red-50 text-red-600 text-xs rounded-xl border border-red-200">
          {{ errorModal }}
        </div>

        <form @submit.prevent="guardarNuevaContrasena" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nueva Contraseña</label>
            
            <div class="relative">
              <input 
                v-model="nuevaContrasena"
                :type="mostrarPassword ? 'text' : 'password'" 
                required
                placeholder="••••••••"
                class="w-full px-4 py-3 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all"
              />
              <button 
                type="button"
                @click="mostrarPassword = !mostrarPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors"
                title="Mostrar u ocultar contraseña"
              >
                <svg v-if="!mostrarPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.059 10.059 0 013.999-5.383m2.28-1.503A10.05 10.05 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.057 10.057 0 01-2.28 3.583m-1.618 1.618A9.956 9.956 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 011.618-3.583m0 0L3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>

          <div class="text-[11px] text-gray-500 bg-gray-50 p-3 rounded-xl space-y-1">
            <p class="font-semibold text-gray-700">La clave debe contener:</p>
            <p>• Mínimo 8 caracteres</p>
            <p>• Al menos 1 mayúscula, 1 número y 1 carácter especial</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="cerrarModal"
              class="px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition-all"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="guardandoPassword"
              class="px-4 py-2.5 bg-amber-500 text-white text-xs font-bold rounded-xl hover:bg-amber-600 disabled:opacity-50 transition-all shadow-md"
            >
              {{ guardandoPassword ? 'Guardando...' : 'Actualizar Clave' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL DE DETALLE DE SOLICITUD DE ORGANIZADOR -->
    <!-- ========================================== -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="detalleAbierto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="cerrarDetalleSolicitud"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="detalleAbierto && solicitudDetalle" class="bg-white rounded-3xl shadow-2xl max-w-lg w-full border border-gray-100 overflow-hidden max-h-[85vh] flex flex-col">
            <!-- Encabezado -->
            <div class="px-7 pt-7 pb-5 border-b border-gray-100 shrink-0">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-bold text-indigo-600 uppercase tracking-wide">Solicitud de organizador</p>
                  <h3 class="text-lg font-extrabold text-gray-900 mt-0.5">{{ solicitudDetalle.nombre }}</h3>
                  <p class="text-xs text-gray-500 font-mono mt-0.5">{{ solicitudDetalle.correo }}</p>
                </div>
                <button @click="cerrarDetalleSolicitud" class="text-gray-400 hover:text-gray-600 font-bold text-xl leading-none shrink-0">✕</button>
              </div>
            </div>

            <!-- Cuerpo -->
            <div class="px-7 py-6 space-y-6 overflow-y-auto">
              <div v-if="!datosRecinto(solicitudDetalle)" class="text-sm text-gray-400 italic text-center py-6">
                Esta solicitud no tiene datos de recinto registrados.
              </div>
              <template v-else>
                <div>
                  <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Información de contacto</p>
                  <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">Teléfono</dt>
                      <dd class="text-gray-900 font-medium mt-0.5">{{ datosRecinto(solicitudDetalle).telefono || 'No especificado' }}</dd>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">RFC / ID fiscal</dt>
                      <dd class="text-gray-900 font-medium mt-0.5">{{ datosRecinto(solicitudDetalle).rfc || 'No especificado' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Datos del recinto</p>
                  <dl class="space-y-3 text-sm">
                    <div class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">Nombre</dt>
                      <dd class="text-gray-900 font-bold mt-0.5">{{ datosRecinto(solicitudDetalle).nombre }}</dd>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">Dirección</dt>
                      <dd class="text-gray-900 font-medium mt-0.5">📍 {{ datosRecinto(solicitudDetalle).direccion || 'Sin dirección' }}</dd>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">Capacidad</dt>
                      <dd class="text-gray-900 font-medium mt-0.5">👥 {{ datosRecinto(solicitudDetalle).capacidad || 'N/A' }} personas</dd>
                    </div>
                    <div v-if="datosRecinto(solicitudDetalle).descripcion" class="bg-gray-50 rounded-xl p-3">
                      <dt class="text-[11px] text-gray-500 font-semibold">Descripción</dt>
                      <dd class="text-gray-700 mt-0.5 leading-relaxed">{{ datosRecinto(solicitudDetalle).descripcion }}</dd>
                    </div>
                  </dl>
                </div>
              </template>
            </div>

            <!-- Acciones -->
            <div class="px-7 py-5 border-t border-gray-100 bg-gray-50/60 flex gap-3 shrink-0">
              <button
                @click="rechazarDesdeDetalle(solicitudDetalle.id)"
                :disabled="procesandoAccion === solicitudDetalle.id"
                class="flex-1 py-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl text-sm font-bold transition-all disabled:opacity-50"
              >
                ❌ Rechazar
              </button>
              <button
                @click="aprobarDesdeDetalle(solicitudDetalle.id)"
                :disabled="procesandoAccion === solicitudDetalle.id"
                class="flex-1 py-3 bg-emerald-600 text-white hover:bg-emerald-700 rounded-xl text-sm font-bold transition-all shadow-md disabled:opacity-50"
              >
                ✅ Aprobar
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { axios, useAuth } from '../composables/useAuth';
import AdminLogs from '../Components/AdminLogs.vue';

const { user: usuarioActual } = useAuth();

// Estado General
const pestañaActiva = ref('usuarios'); // 'usuarios' | 'solicitudes' | 'logs'
const usuarios = ref([]);
const solicitudes = ref([]);
const cargando = ref(true);
const cargandoSolicitudes = ref(false);
const procesandoAccion = ref(null);
const mensaje = ref('');
const errorGeneral = ref('');

// Estado de Búsqueda y Filtros
const busqueda = ref('');
const rolSeleccionado = ref('');

// Estado del Modal (Original)
const modalAbierto = ref(false);
const usuarioSeleccionado = ref(null);
const nuevaContrasena = ref('');
const mostrarPassword = ref(false);
const guardandoPassword = ref(false);
const errorModal = ref('');

// --- PROPIEDADES COMPUTADAS PARA FILTRADO REACTIVO ---

// 1. Usuarios filtrados por Búsqueda y Rol
const usuariosFiltrados = computed(() => {
  return usuarios.value.filter(u => {
    const query = busqueda.value.toLowerCase().trim();
    const rolNormalizado = u.rol === 'comprador' ? 'cliente' : (u.rol || 'cliente');

    // Filtro por texto (Nombre, Correo o ID)
    const coincideTexto = !query || 
      u.nombre?.toLowerCase().includes(query) ||
      u.correo?.toLowerCase().includes(query) ||
      String(u.id).includes(query);

    // Filtro por Rol
    const coincideRol = !rolSeleccionado.value || rolNormalizado === rolSeleccionado.value;

    return coincideTexto && coincideRol;
  });
});

// Extrae los datos de recinto/contacto de una solicitud, sin importar si
// vinieron del auto-servicio (solicitud_organizador, con teléfono/RFC/
// descripción) o del registro directo de invitado (teatros[0], que ya crea el
// Teatro al registrarse y no tiene esos campos de contacto). null si ninguno
// de los dos existe (no debería pasar en la práctica, pero evita reventar la
// tabla si algún día sí).
const datosRecinto = (solicitud) => {
  if (solicitud.solicitud_organizador) {
    const s = solicitud.solicitud_organizador;
    return {
      nombre: s.recinto_nombre,
      direccion: s.recinto_direccion,
      capacidad: s.recinto_capacidad,
      telefono: s.telefono_contacto,
      rfc: s.rfc,
      descripcion: s.descripcion,
    };
  }
  if (solicitud.teatros?.length > 0) {
    const t = solicitud.teatros[0];
    return {
      nombre: t.nombre,
      direccion: t.ubicacion,
      capacidad: t.capacidad_total,
      telefono: null,
      rfc: null,
      descripcion: null,
    };
  }
  return null;
};

// 2. Solicitudes filtradas por Búsqueda
const solicitudesFiltradas = computed(() => {
  return solicitudes.value.filter(s => {
    const query = busqueda.value.toLowerCase().trim();
    const recinto = datosRecinto(s);

    return !query ||
      s.nombre?.toLowerCase().includes(query) ||
      s.correo?.toLowerCase().includes(query) ||
      recinto?.nombre?.toLowerCase().includes(query) ||
      recinto?.direccion?.toLowerCase().includes(query);
  });
});

// --- MODAL DE DETALLE DE SOLICITUD ---
const detalleAbierto = ref(false);
const solicitudDetalle = ref(null);

const abrirDetalleSolicitud = (solicitud) => {
  solicitudDetalle.value = solicitud;
  detalleAbierto.value = true;
};

const cerrarDetalleSolicitud = () => {
  detalleAbierto.value = false;
  solicitudDetalle.value = null;
};

// Acciones lanzadas desde el modal de detalle: reutilizan aprobarOrganizador/
// rechazarOrganizador (definidas más abajo) y cierran el modal al terminar.
const aprobarDesdeDetalle = async (id) => {
  await aprobarOrganizador(id);
  cerrarDetalleSolicitud();
};

const rechazarDesdeDetalle = async (id) => {
  await rechazarOrganizador(id);
  cerrarDetalleSolicitud();
};

const limpiarFiltros = () => {
  busqueda.value = '';
  rolSeleccionado.value = '';
};

// Cargar Usuarios (Original)
const cargarUsuarios = async () => {
  cargando.value = true;
  try {
    const res = await axios.get('/api/admin/usuarios');
    usuarios.value = res.data;
  } catch (err) {
    errorGeneral.value = 'Error al cargar los usuarios.';
  } finally {
    cargando.value = false;
  }
};

// Cargar Solicitudes de Organizador (Original)
const cargarSolicitudes = async () => {
  cargandoSolicitudes.value = true;
  try {
    const res = await axios.get('/api/admin/solicitudes-organizador');
    solicitudes.value = res.data;
  } catch (err) {
    errorGeneral.value = 'Error al cargar solicitudes.';
  } finally {
    cargandoSolicitudes.value = false;
  }
};

const recargarTodo = () => {
  cargarUsuarios();
  cargarSolicitudes();
};

// Actualizar Rol (Original)
const actualizarRol = async (id, nuevoRol) => {
  mensaje.value = '';
  errorGeneral.value = '';
  try {
    await axios.put(`/api/admin/usuarios/${id}/rol`, { rol: nuevoRol });

    mensaje.value = `El rol del usuario #${id} fue actualizado a "${nuevoRol}" con éxito.`;
    const idx = usuarios.value.findIndex(u => u.id === id);
    if (idx !== -1) usuarios.value[idx].rol = nuevoRol;
  } catch (err) {
    errorGeneral.value = err.response?.data?.message || 'Error al actualizar el rol del usuario.';
  }
};

// Eliminar Usuario (Original)
const eliminarUsuario = async (id, correo) => {
  if (!confirm(`¿Estás seguro de eliminar lógicamente al usuario #${id} (${correo})?`)) return;

  mensaje.value = '';
  errorGeneral.value = '';
  try {
    await axios.delete(`/api/admin/usuarios/${id}`);

    mensaje.value = `El usuario #${id} ha sido desactivado (borrado lógico).`;
    usuarios.value = usuarios.value.filter(u => String(u.id) !== String(id));
  } catch (err) {
    errorGeneral.value = err.response?.data?.message || 'Error al intentar eliminar el usuario.';
  }
};

// Aprobar Solicitud de Organizador (Original)
const aprobarOrganizador = async (id) => {
  procesandoAccion.value = id;
  mensaje.value = '';
  errorGeneral.value = '';
  try {
    const res = await axios.put(`/api/admin/organizador/${id}/aprobar`);

    mensaje.value = res.data.mensaje || 'Solicitud aprobada con éxito. Se envió correo de notificación.';
    solicitudes.value = solicitudes.value.filter(s => s.id !== id);
    cargarUsuarios(); // Recargar la tabla de usuarios para reflejar el nuevo rol
  } catch (err) {
    errorGeneral.value = err.response?.data?.mensaje || err.response?.data?.message || 'Error al aprobar la solicitud.';
  } finally {
    procesandoAccion.value = null;
  }
};

// Rechazar Solicitud de Organizador (Original)
const rechazarOrganizador = async (id) => {
  if (!confirm('¿Estás seguro de rechazar esta solicitud?')) return;

  procesandoAccion.value = id;
  mensaje.value = '';
  errorGeneral.value = '';
  try {
    const res = await axios.put(`/api/admin/organizador/${id}/rechazar`);

    mensaje.value = res.data.mensaje || 'Solicitud rechazada.';
    solicitudes.value = solicitudes.value.filter(s => s.id !== id);
  } catch (err) {
    errorGeneral.value = err.response?.data?.mensaje || err.response?.data?.message || 'Error al rechazar la solicitud.';
  } finally {
    procesandoAccion.value = null;
  }
};

// MODAL CONTRASEÑA (Original)
const abrirModalPassword = (usuario) => {
  usuarioSeleccionado.value = usuario;
  nuevaContrasena.value = '';
  mostrarPassword.value = false;
  errorModal.value = '';
  modalAbierto.value = true;
};

const cerrarModal = () => {
  modalAbierto.value = false;
  usuarioSeleccionado.value = null;
  nuevaContrasena.value = '';
  mostrarPassword.value = false;
  errorModal.value = '';
};

const guardarNuevaContrasena = async () => {
  if (!nuevaContrasena.value) return;

  guardandoPassword.value = true;
  errorModal.value = '';

  try {
    await axios.put(`/api/admin/usuarios/${usuarioSeleccionado.value.id}/contrasena`, {
      contrasena: nuevaContrasena.value
    });

    mensaje.value = `Contraseña actualizada con éxito para ${usuarioSeleccionado.value.correo}.`;
    cerrarModal();
  } catch (err) {
    const data = err.response?.data;
    errorModal.value = data
      ? (data.message || data.error || (data.errors ? Object.values(data.errors).flat()[0] : 'Error al actualizar contraseña.'))
      : 'Ocurrió un error de conexión con el servidor.';
  } finally {
    guardandoPassword.value = false;
  }
};

onMounted(() => {
  cargarUsuarios();
  cargarSolicitudes();
});
</script>