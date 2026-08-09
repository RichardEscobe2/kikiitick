<template>
  <!-- Solo mobile (oculta en sm+, ver MainLayout.vue) — Tickets/Perfil ya están
       protegidas por requiresAuth en el router; navegar sin sesión redirige a
       Login automáticamente, no hace falta lógica extra aquí.
       Nota: el mockup incluye una 4ª pestaña "Favoritos" que se omitió a
       propósito — no existe ninguna función de favoritos en el backend, y un
       tab que no lleva a ningún lado es peor que no tenerlo. -->
  <nav class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-100 flex items-stretch pb-[env(safe-area-inset-bottom)]">
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

    <router-link
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
      to="/perfil"
      class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-semibold transition-colors"
      :class="$route.name === 'Perfil' ? 'text-indigo-600' : 'text-gray-400'"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      Profile
    </router-link>
  </nav>
</template>
