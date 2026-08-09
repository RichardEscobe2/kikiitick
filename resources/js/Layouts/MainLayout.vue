<template>
  <div class="min-h-screen bg-[#f0f2f5] flex flex-col transition-colors">
    <!-- Navbar Global -->
    <Navbar />

    <!-- Área principal donde se renderizan las vistas. pb-16 en mobile deja
         espacio para que BottomTabBar (fija, position: fixed) no tape el final
         del contenido/footer — sm:pb-0 porque en desktop esa barra no existe. -->
    <main class="flex-1 flex flex-col" :class="mostrarTabBarInferior ? 'pb-16 sm:pb-0' : ''">
      <router-view />
    </main>

    <Footer />

    <!-- Oculta en herramientas de staff (POS/Admin/Organizador): son pantallas
         de trabajo con su propia navegación aislada (ver Navbar.vue para
         cajeros) — una barra de navegación de cliente (Discover/Tickets/
         Profile) ahí competiría con ese flujo en vez de ayudar. -->
    <BottomTabBar v-if="mostrarTabBarInferior" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Navbar from '../Components/Navbar.vue';
import Footer from '../Components/Footer.vue';
import BottomTabBar from '../Components/BottomTabBar.vue';

const route = useRoute();

const rutasSinTabBar = ['POSTaquilla', 'AdminUsuarios', 'Organizador'];
const mostrarTabBarInferior = computed(() => !rutasSinTabBar.includes(route.name));
</script>
