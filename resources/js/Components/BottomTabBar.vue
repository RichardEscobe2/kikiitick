<template>
  <!-- Solo mobile (md:hidden — oculta en md+, ver MainLayout.vue).
       Regla de esta iteración: en mobile, cualquier acción de login (Ingresar
       o Profile en estado invitado) navega a /login en vez de abrir el modal
       — el guard del router (requiresAuth) ya no importa aquí porque nunca
       navegamos primero a la ruta protegida, vamos directo a /login.
       Nota: el mockup incluye una 4ª pestaña "Favoritos" que se omitió a
       propósito — no existe ninguna función de favoritos en el backend, y un
       tab que no lleva a ningún lado es peor que no tenerlo. -->
  <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-100 flex items-stretch pb-[env(safe-area-inset-bottom)]">
    <router-link
      to="/"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold transition-colors"
      :class="$route.name === 'Home' ? 'text-indigo-600' : 'text-gray-400'"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      Discover
    </router-link>

    <!-- TICKETS: con sesión navega a Mis Boletos; invitado se convierte en un
         tab de "Ingresar" con ícono de login, que lleva a /login. -->
    <router-link
      v-if="isAuthenticated"
      to="/mis-boletos"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold transition-colors"
      :class="$route.name === 'MisBoletos' ? 'text-indigo-600' : 'text-gray-400'"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h2m6-14h2a2 2 0 012 2v3a2 2 0 100 4v3a2 2 0 01-2 2h-2M9 5v14" />
      </svg>
      Tickets
    </router-link>
    <router-link
      v-else
      to="/login"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold text-gray-400 transition-colors"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
      </svg>
      Ingresar
    </router-link>

    <!-- PROFILE: con sesión navega a Perfil (que ya incluye "Cerrar Sesión");
         invitado navega a /login — el modal queda reservado para flujos de
         perfil, no para el login en sí en mobile. -->
    <router-link
      v-if="isAuthenticated"
      to="/perfil"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold transition-colors"
      :class="$route.name === 'Perfil' ? 'text-indigo-600' : 'text-gray-400'"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      Profile
    </router-link>
    <router-link
      v-else
      to="/login"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold text-gray-400 transition-colors"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      Profile
    </router-link>
  </nav>
</template>

<script setup>
import { useAuth } from '../composables/useAuth';

const { isAuthenticated } = useAuth();
</script>
