<template>
  <!-- Cambiado bg-gray-50 por bg-slate-100 para dar más contraste general -->
  <div class="min-h-screen bg-[#f0f2f5] flex flex-col transition-colors">
    <!-- Navbar Global -->
    <Navbar />

    <!-- Área principal donde se renderizan las vistas -->
    <main class="flex-1 flex flex-col">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import Navbar from '../Components/Navbar.vue'; 
</script>