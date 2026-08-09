<template>
  <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300 border border-gray-100 flex flex-col justify-between h-full">
    <div>
      <img 
        :src="evento?.imagen_url" 
        :alt="evento?.titulo" 
        class="w-full h-48 object-cover"
        @error="e => e.target.src = 'https://via.placeholder.com/400x200?text=Evento+KikiiTick'"
      />
      <div class="p-5">
        <span class="inline-block px-3 py-1 text-xs font-semibold text-indigo-600 bg-indigo-50 rounded-full mb-2">
          {{ evento?.categoria }}
        </span>
        <h3 class="text-xl font-bold text-gray-900 line-clamp-1">{{ evento?.titulo }}</h3>
        <p class="text-gray-500 text-sm mt-2 line-clamp-2">{{ evento?.descripcion }}</p>
      </div>
    </div>

    <div class="p-5 pt-0 flex items-center justify-between border-t border-gray-50 mt-4">
      <span class="text-xs text-gray-400 font-medium truncate max-w-[150px]">📍 {{ evento?.teatro_nombre }}</span>
      <button 
        @click.stop="irAlEvento"
        type="button"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all shadow-md cursor-pointer shrink-0"
      >
        Ver boletos
      </button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
  evento: Object
});

const router = useRouter();

const irAlEvento = () => {
  if (props.evento && props.evento.id) {
    router.push({ name: 'EventoLanding', params: { id: props.evento.id } });
  }
};
</script>