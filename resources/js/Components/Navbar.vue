<template>
  <header class="bg-indigo-600 text-white shadow-md relative">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      
      <!-- LOGO -->
      <router-link to="/" class="flex items-center space-x-2 text-xl font-bold">
        <span>KikiiTick</span>
      </router-link>


      <!-- BOTÓN / ÍCONO DE PERFIL -->
      <div class="relative">
        
        <!-- OPCCIÓN A: Sin sesión (Boceto 1) -->
        <router-link 
          v-if="!isAuthenticated" 
          to="/login" 
          class="bg-white text-indigo-600 px-4 py-1.5 rounded-full font-semibold text-sm hover:bg-gray-100 transition"
        >
          Iniciar Sesión
        </router-link>

        <!-- OPCCIÓN B: Con sesión iniciada (Boceto 4) -->
        <button 
          v-else 
          @click="menuAbierto = !menuAbierto" 
          class="w-10 h-10 rounded-full bg-indigo-700 border-2 border-white flex items-center justify-center hover:bg-indigo-800 focus:outline-none"
        >
          <!-- Ícono de Perfil -->
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </button>

        <!-- MENÚ DESPLEGABLE / LATERAL (Boceto 4.1) -->
        <div 
          v-if="isAuthenticated && menuAbierto" 
          class="absolute right-0 mt-2 w-56 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
        >
          <!-- Header del usuario -->
          <div class="p-3 border-b bg-gray-50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
              👤
            </div>
            <div class="truncate">
              <p class="text-sm font-semibold truncate">{{ user?.nombre || 'Usuario' }}</p>
              <p class="text-xs text-gray-500 capitalize">{{ user?.rol }}</p>
            </div>
          </div>

          <!-- 🏧 Menú aislado para cajeros: sin accesos de cliente/administración —
               solo la identidad del turno (cajero + caja asignada) y cierre de sesión.
               El cajero llega directo a /pos al iniciar sesión (Login.vue), así que
               tampoco necesita un enlace de navegación duplicado aquí. -->
          <div v-if="isVendedor" class="py-2">
            <div class="px-4 py-2 text-xs text-gray-500">
              <p class="font-semibold text-gray-700">🏧 Cajero: {{ user?.nombre }}</p>
              <p>Caja: {{ user?.taquilla?.nombre || 'Sin asignar' }}</p>
            </div>

            <button
              @click="handleLogout"
              class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t mt-1 transition font-medium"
            >
              🚪 Cerrar Sesión
            </button>
          </div>

          <!-- Opciones del Menú (clientes, organizadores y administradores) -->
          <div v-else class="py-2">
            <router-link
              to="/perfil"
              @click="menuAbierto = false"
              class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition"
            >
              👤 Mi Perfil
            </router-link>

            <router-link
              to="/mis-boletos"
              @click="menuAbierto = false"
              class="block px-4 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-600 transition"
            >
              🎫 Mis Boletos
            </router-link>

            <!-- Acceso directo si es Admin o Organizador -->
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

            <button
              @click="handleLogout"
              class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t mt-1 transition font-medium"
            >
              🚪 Cerrar Sesión
            </button>
          </div>
        </div>

      </div>

    </div>
  </header>
</template>

<script setup>
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth';

const { user, isAuthenticated, userRole, isVendedor, logout } = useAuth();
const menuAbierto = ref(false);

const handleLogout = () => {
  menuAbierto.value = false;
  logout();
};
</script>