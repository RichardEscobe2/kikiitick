<template>
  <div class="text-center py-16 px-4 bg-white rounded-2xl shadow-sm border border-gray-100 max-w-xl mx-auto my-8">
    <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
      🎟️
    </div>
    <h3 class="text-xl font-bold text-gray-800">¡Aún no hay eventos disponibles!</h3>
    <p class="text-gray-500 mt-2 text-sm leading-relaxed">
      Estamos preparando la cartelera para los mejores espectáculos. Vuelve pronto o sé de los primeros en publicar.
    </p>
    <div class="mt-6 flex justify-center gap-3">
      <button
        @click="emit('cta-click')"
        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl transition-all shadow-sm"
      >
        ¿Eres organizador? Crea un evento
      </button>
    </div>
  </div>
</template>

<script setup>
const emit = defineEmits(['cta-click']);
</script>