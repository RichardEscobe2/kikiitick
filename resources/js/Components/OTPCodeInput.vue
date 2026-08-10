<template>
  <div class="flex items-center justify-center gap-2 sm:gap-3" @paste="onPaste">
    <input
      v-for="(_, i) in digitos"
      :key="i"
      :ref="el => { if (el) inputRefs[i] = el }"
      v-model="digitos[i]"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      pattern="[0-9]*"
      maxlength="1"
      :disabled="disabled"
      :aria-label="`Dígito ${i + 1} del código`"
      class="w-11 h-14 sm:w-12 sm:h-14 text-center text-xl font-bold text-slate-900 bg-slate-50 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150"
      @input="onInput(i, $event)"
      @keydown.backspace="onBackspace(i)"
      @focus="$event.target.select()"
    />
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';

const code = defineModel({ type: String, default: '' });
defineProps({
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['complete']);

const digitos = ref(['', '', '', '', '', '']);
const inputRefs = ref([]);

// 🐛 Encontrado en QA visual real: sin { immediate: true }, watch() NO corre
// al montar el componente — solo reacciona a cambios POSTERIORES. Un valor
// inicial del v-model (ej. código restaurado, o cualquier asignación externa
// antes de que el usuario interactúe) se perdía silenciosamente, dejando las
// 6 cajas vacías aunque `code` ya tuviera un valor real.
watch(code, (valor) => {
  const nuevos = (valor || '').replace(/\D/g, '').slice(0, 6).split('');
  const actual = digitos.value.join('');
  if (actual === nuevos.join('')) return; // evita loop: ya coinciden
  digitos.value = [...nuevos, ...Array(6 - nuevos.length).fill('')];
}, { immediate: true });

const sincronizar = () => {
  const valor = digitos.value.join('');
  code.value = valor;
  if (valor.length === 6) emit('complete', valor);
};

const onInput = (i, e) => {
  const limpio = e.target.value.replace(/\D/g, '').slice(-1);
  digitos.value[i] = limpio;
  sincronizar();
  if (limpio && i < 5) {
    nextTick(() => inputRefs.value[i + 1]?.focus());
  }
};

const onBackspace = (i) => {
  if (!digitos.value[i] && i > 0) {
    nextTick(() => inputRefs.value[i - 1]?.focus());
  }
};

const onPaste = (e) => {
  e.preventDefault();
  const texto = (e.clipboardData || window.clipboardData)?.getData('text') || '';
  const numeros = texto.replace(/\D/g, '').slice(0, 6).split('');
  if (numeros.length === 0) return;

  digitos.value = [...numeros, ...Array(6 - numeros.length).fill('')];
  sincronizar();

  const siguienteIndex = Math.min(numeros.length, 5);
  nextTick(() => inputRefs.value[siguienteIndex]?.focus());
};
</script>
