<template>
  <footer id="footer" class="py-6 border-t border-slate-100 text-center text-sm text-slate-500">
    © {{ anioActual }} KikiiTick. Todos los derechos reservados.
  </footer>
</template>

<script setup>
const anioActual = new Date().getFullYear();
</script>
