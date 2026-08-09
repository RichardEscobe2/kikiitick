<template>
  <header class="bg-white border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- ============ DESKTOP (sm+) ============ -->
      <div class="hidden sm:flex items-center justify-between h-16">
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
              :to="enlaceOrganizar"
              class="pb-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition-colors"
            >
              Organizar
            </router-link>
            <a href="#footer" class="pb-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 transition-colors">
              Nosotros
            </a>
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

      <!-- ============ MOBILE (<sm): header simplificado ============ -->
      <div class="flex sm:hidden items-center justify-between h-14">
        <router-link :to="isAuthenticated ? '/mis-boletos' : '/login'" class="p-2 -ml-2 text-gray-700" title="Mis boletos">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h2m6-14h2a2 2 0 012 2v3a2 2 0 100 4v3a2 2 0 01-2 2h-2M9 5v14" />
          </svg>
        </router-link>

        <router-link to="/" class="text-lg font-extrabold text-indigo-600">KikiiTick</router-link>

        <div class="relative">
          <button @click="mostrarAvisoNotificaciones = !mostrarAvisoNotificaciones" class="p-2 -mr-2 text-gray-700 cursor-pointer" title="Notificaciones">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </button>
          <!-- 🛡️ Sin backend de notificaciones — feedback honesto en vez de un
               dropdown con contenido inventado. -->
          <div
            v-if="mostrarAvisoNotificaciones"
            class="absolute right-0 mt-2 w-52 bg-white text-gray-600 text-xs rounded-xl shadow-xl border border-gray-100 p-3 z-50"
          >
            Aún no tienes notificaciones nuevas.
          </div>
        </div>
      </div>

    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuth } from '../composables/useAuth';

const { user, isAuthenticated, userRole, isVendedor, logout } = useAuth();
const menuAbierto = ref(false);
const mostrarAvisoNotificaciones = ref(false);

const inicialUsuario = computed(() => (user.value?.nombre || 'U').trim().charAt(0).toUpperCase());

// "Organizar": a un organizador aprobado lo manda directo a su panel; a
// cualquier otro rol (incluido sin sesión) lo invita a solicitar cuenta de
// organizador — nunca un enlace muerto.
const enlaceOrganizar = computed(() => (userRole.value === 'organizador' ? '/organizador' : '/registro-organizador'));

const handleLogout = () => {
  menuAbierto.value = false;
  logout();
};
</script>
