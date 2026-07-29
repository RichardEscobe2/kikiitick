<template>
  <div class="max-w-4xl mx-auto my-10 p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-bold text-gray-800 border-b pb-4">Mi Perfil 👤</h1>
    
    <div class="mt-6 space-y-4">
      <div>
        <label class="text-sm text-gray-500 font-semibold block">Nombre:</label>
        <p class="text-gray-800 text-lg font-medium">{{ user?.nombre || 'Cargando...' }}</p>
      </div>

      <div>
        <label class="text-sm text-gray-500 font-semibold block">Correo Electrónico:</label>
        <p class="text-gray-800 text-lg font-medium">{{ user?.email || user?.correo || 'Cargando...' }}</p>
      </div>

      <div>
        <label class="text-sm text-gray-500 font-semibold block">Rol de Usuario:</label>
        <span class="inline-block mt-1 px-3 py-1 bg-indigo-100 text-indigo-700 font-semibold text-sm rounded-full capitalize">
          {{ user?.rol || 'Cliente' }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();
</script>