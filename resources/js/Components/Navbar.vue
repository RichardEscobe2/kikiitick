<template>
  <header class="bg-white border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- ============ DESKTOP (md+) ============ -->
      <div class="hidden md:flex items-center justify-between h-16">
        <div class="flex items-center gap-8">
          <router-link to="/" class="text-xl font-extrabold text-indigo-600 shrink-0">KikiiTick</router-link>

          <nav class="flex items-center gap-6 text-sm font-semibold">
            <router-link
              to="/"
              class="pb-1 border-b-2 transition-colors"
              :class="$route.name === 'Home' ? 'text-indigo-600 border-indigo-600' : 'text-gray-600 border-transparent hover:text-gray-900'"
            >
              Explorar
            </router-link>
            <router-link
              v-if="enlaceOrganizar"
              :to="enlaceOrganizar"
              class="pb-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition-colors"
            >
              Organizar
            </router-link>
            <button
              v-else
              type="button"
              @click="abrirSolicitudModal"
              class="pb-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition-colors cursor-pointer"
            >
              Organizar
            </button>
          </nav>
        </div>

        <!-- Sin sesión: par Iniciar Sesión / Crear Cuenta -->
        <div v-if="!isAuthenticated" class="flex items-center gap-3 shrink-0">
          <router-link to="/login" class="text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors">
            Iniciar Sesión
          </router-link>
          <router-link
            to="/registro"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-full transition-colors"
          >
            Crear Cuenta
          </router-link>
        </div>

        <!-- Con sesión: menú de perfil -->
        <div v-else class="relative shrink-0">
          <button
            @click="menuAbierto = !menuAbierto"
            class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center hover:bg-indigo-200 transition-colors cursor-pointer"
          >
            {{ inicialUsuario }}
          </button>

          <div
            v-if="menuAbierto"
            class="absolute right-0 mt-2 w-56 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
          >
            <div class="p-3 border-b bg-gray-50 flex items-center space-x-3">
              <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                {{ inicialUsuario }}
              </div>
              <div class="truncate">
                <p class="text-sm font-semibold truncate">{{ user?.nombre || 'Usuario' }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ user?.rol }}</p>
              </div>
            </div>

            <div v-if="isVendedor" class="py-2">
              <div class="px-4 py-2 text-xs text-gray-500">
                <p class="font-semibold text-gray-700">🏧 Cajero: {{ user?.nombre }}</p>
                <p>Caja: {{ user?.taquilla?.nombre || 'Sin asignar' }}</p>
              </div>
              <button @click="handleLogout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t mt-1 transition font-medium cursor-pointer">
                🚪 Cerrar Sesión
              </button>
            </div>

            <div v-else class="py-2">
              <router-link to="/perfil" @click="menuAbierto = false" class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition">
                👤 Mi Perfil
              </router-link>
              <router-link to="/mis-boletos" @click="menuAbierto = false" class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition">
                🎫 Mis Boletos
              </router-link>
              <router-link
                v-if="userRole === 'admin'"
                to="/admin/usuarios"
                @click="menuAbierto = false"
                class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition font-medium text-indigo-600"
              >
                ⚙️ Panel de Administración
              </router-link>
              <router-link
                v-if="userRole === 'organizador'"
                to="/organizador"
                @click="menuAbierto = false"
                class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition font-medium text-indigo-600"
              >
                🎪 Panel Organizador
              </router-link>
              <router-link
                v-if="['organizador', 'admin'].includes(userRole)"
                to="/pos"
                @click="menuAbierto = false"
                class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition font-medium text-emerald-700"
              >
                🏧 Taquilla (POS)
              </router-link>
              <button @click="handleLogout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t mt-1 transition font-medium cursor-pointer">
                🚪 Cerrar Sesión
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ MOBILE (<md): header simplificado ============ -->
      <!-- md:hidden es lo que evita que este bloque se apile encima del
           desktop (bug real detectado: se había perdido al reescribir este
           div a grid en la iteración anterior — sin esta clase, ambos
           bloques se mostraban a la vez desde 640px en adelante).
           Grid de 3 columnas (no flex justify-between) para que el logo quede
           realmente centrado aunque la columna derecha esté vacía — ver
           tailwind-best-practices sobre alineación de íconos: un flex con solo
           2 elementos habría empujado el logo hacia la derecha en vez de
           centrarlo. -->
      <div class="md:hidden grid grid-cols-3 items-center h-14">
        <div class="relative justify-self-start">
          <!-- Hamburguesa: abre un dropdown con los enlaces que en desktop
               viven en la barra de navegación (Organizar) — el header móvil
               no tiene espacio para mostrarlos sueltos. -->
          <button
            type="button"
            @click="menuMovilAbierto = !menuMovilAbierto"
            class="p-2 -ml-2 text-gray-700 cursor-pointer"
            title="Menú"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div
            v-if="menuMovilAbierto"
            class="absolute left-0 mt-2 w-48 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden py-2"
          >
            <router-link
              v-if="enlaceOrganizar"
              :to="enlaceOrganizar"
              @click="menuMovilAbierto = false"
              class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition"
            >
              Organizar
            </router-link>
            <button
              v-else
              type="button"
              @click="handleOrganizarMovil"
              class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition cursor-pointer"
            >
              Organizar
            </button>
          </div>
        </div>

        <router-link to="/" class="justify-self-center text-lg font-extrabold text-indigo-600">KikiiTick</router-link>

        <!-- Con sesión: acceso directo a Perfil. Sin sesión: navega a la
             vista completa de Login (no abre el modal) — regla explícita de
             la iteración anterior: en mobile, cualquier acción de login lleva
             a /login, el modal queda reservado para flujos de perfil. -->
        <router-link v-if="isAuthenticated" to="/perfil" class="justify-self-end inline-flex p-2 -mr-2 text-gray-700" title="Mi perfil">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </router-link>
        <router-link v-else to="/login" class="justify-self-end inline-flex p-2 -mr-2 text-gray-700" title="Iniciar sesión">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </router-link>
      </div>

    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuth } from '../composables/useAuth';
import { useSolicitudOrganizadorModal } from '../composables/useSolicitudOrganizadorModal';

const { user, isAuthenticated, userRole, isVendedor, logout } = useAuth();
const { abrirSolicitudModal } = useSolicitudOrganizadorModal();
const menuAbierto = ref(false);
const menuMovilAbierto = ref(false);

const inicialUsuario = computed(() => (user.value?.nombre || 'U').trim().charAt(0).toUpperCase());

// "Organizar" depende de la sesión y el rol — nunca debe ser un enlace muerto:
// - Invitado: formulario público de registro de organizador (crea cuenta + Teatro).
// - organizador aprobado / admin: directo a su panel real (no hay rutas
//   /organizador/dashboard ni /admin/dashboard en este proyecto — son
//   /organizador y /admin/usuarios).
// - cliente (y cualquier otro rol autenticado, ej. vendedor): null → no navega,
//   abre el modal de solicitud (botón desktop llama abrirSolicitudModal
//   directo; mobile pasa por handleOrganizarMovil para cerrar el dropdown antes).
//   Antes de este fix, cualquier usuario autenticado no-organizador caía en
//   '/registro-organizador', que tiene meta:{guestOnly:true} — el guard del
//   router lo rebotaba a Home en silencio.
const enlaceOrganizar = computed(() => {
  if (!isAuthenticated.value) return '/registro-organizador';
  if (userRole.value === 'organizador') return '/organizador';
  if (userRole.value === 'admin') return '/admin/usuarios';
  return null;
});

const handleOrganizarMovil = () => {
  menuMovilAbierto.value = false;
  abrirSolicitudModal();
};

const handleLogout = () => {
  menuAbierto.value = false;
  logout();
};
</script>
