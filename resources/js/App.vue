<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuth } from './composables/useAuth';

const { fetchUser } = useAuth();

// Valida la sesión activa con el servidor al cargar la app
onMounted(() => {
  fetchUser();
});
</script>