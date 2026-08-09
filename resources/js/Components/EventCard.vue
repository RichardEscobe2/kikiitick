<template>
  <div
    class="group bg-white rounded-2xl shadow-sm hover:shadow-xl transition-shadow duration-300 border border-gray-100 overflow-hidden flex flex-col h-full cursor-pointer"
    @click="irAlEvento"
  >
    <!-- IMAGEN + BADGE DE CATEGORÍA -->
    <div class="relative">
      <img
        :src="evento?.imagen_url"
        :alt="evento?.titulo"
        class="w-full h-44 sm:h-48 object-cover group-hover:scale-105 transition-transform duration-300"
        @error="e => e.target.src = imagenFallback"
        loading="lazy"
      />
      <span
        v-if="evento?.categoria"
        class="absolute top-3 left-3 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white bg-black/60 backdrop-blur-sm rounded-full"
      >
        {{ evento.categoria }}
      </span>
    </div>

    <!-- CONTENIDO -->
    <div class="p-4 sm:p-5 flex flex-col flex-1">
      <h3 class="text-base sm:text-lg font-bold text-gray-900 line-clamp-1">{{ evento?.titulo }}</h3>

      <p class="flex items-center gap-1 text-xs sm:text-sm text-gray-500 mt-1.5">
        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="truncate">{{ evento?.teatro_nombre }}</span>
      </p>

      <div class="mt-auto pt-4 flex items-center justify-between gap-3">
        <div v-if="evento?.precio_desde" class="leading-tight">
          <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Desde</p>
          <p class="text-lg font-extrabold text-gray-900">${{ precioFormateado }}</p>
        </div>
        <p v-else class="text-xs text-gray-400 italic">Precio por confirmar</p>

        <button
          @click.stop="irAlEvento"
          type="button"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all shadow-sm cursor-pointer shrink-0"
        >
          Ver boletos
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
  evento: Object
});

const router = useRouter();

// data: URI en vez de una URL externa (placeholder.com) — no depende de que un
// tercero siga en línea para que la tarjeta se vea bien ante una imagen rota.
const imagenFallback = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200" viewBox="0 0 400 200"><rect width="400" height="200" fill="#e0e7ff"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#6366f1" font-family="sans-serif" font-size="18" font-weight="bold">KikiiTick</text></svg>'
);

const precioFormateado = computed(() => {
  const num = Number(props.evento?.precio_desde || 0);
  return Number.isInteger(num) ? num.toString() : num.toFixed(2);
});

const irAlEvento = () => {
  if (props.evento && props.evento.id) {
    router.push({ name: 'EventoLanding', params: { id: props.evento.id } });
  }
};
</script>
