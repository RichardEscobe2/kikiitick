<template>
  <router-view />
  <QuickAuthModal />
  <SolicitudOrganizadorModal />
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuth } from './composables/useAuth';
import QuickAuthModal from './Components/QuickAuthModal.vue';
import SolicitudOrganizadorModal from './Components/SolicitudOrganizadorModal.vue';

const { fetchUser } = useAuth();

// Valida la sesión activa con el servidor al cargar la app
onMounted(() => {
  fetchUser();
});
</script>