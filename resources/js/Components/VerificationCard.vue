<template>
  <div
    class="max-w-md w-full bg-white rounded-3xl border border-slate-100/80 shadow-xl shadow-slate-200/60 p-8"
    :data-action-type="actionType"
  >
    <!-- Insignia de ícono dual-tone -->
    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/80 ring-1 ring-indigo-100 flex items-center justify-center mx-auto mb-5">
      <svg v-if="iconType === 'shield'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
      <svg v-else class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
    </div>

    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight text-center">{{ title }}</h1>
    <p class="text-sm text-slate-500 text-center mt-2 leading-relaxed">
      {{ subtitle }}
      <span v-if="email" class="font-semibold text-slate-900">{{ email }}</span>
    </p>

    <div v-if="errorMsg" class="mt-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-700 text-xs rounded-r-xl">
      {{ errorMsg }}
    </div>

    <div class="mt-7">
      <OTPCodeInput v-model="code" :disabled="loading" @complete="$emit('complete', $event)" />
    </div>

    <!-- Slot para contenido adicional (ej. campos de contraseña en el flujo de
         recuperación) entre el código y el botón principal. -->
    <slot />

    <button
      type="button"
      :disabled="loading || disableSubmit"
      @click="$emit('submit')"
      class="w-full mt-7 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none transition-all cursor-pointer"
    >
      {{ loading ? 'Procesando...' : buttonText }}
    </button>

    <div v-if="showResend" class="mt-6 text-center border-t border-slate-100 pt-4">
      <button
        type="button"
        :disabled="segundosRestantes > 0 || loading"
        @click="reenviar"
        class="text-xs font-bold transition-colors"
        :class="segundosRestantes > 0 ? 'text-slate-300 cursor-not-allowed' : 'text-indigo-600 hover:text-indigo-800 cursor-pointer'"
      >
        {{ segundosRestantes > 0 ? `Reenviar código en ${segundosRestantes}s` : '¿No recibiste el código? Reenviar' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import OTPCodeInput from './OTPCodeInput.vue';
import { useResendCooldown } from '../composables/useResendCooldown';

const code = defineModel('code', { type: String, default: '' });

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, required: true },
  email: { type: String, default: '' },
  buttonText: { type: String, required: true },
  iconType: { type: String, default: 'mail', validator: (v) => ['mail', 'shield'].includes(v) },
  actionType: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  errorMsg: { type: String, default: '' },
  disableSubmit: { type: Boolean, default: false },
  showResend: { type: Boolean, default: true },
  resendCooldownSeconds: { type: Number, default: 60 },
});

const emit = defineEmits(['submit', 'resend', 'complete']);

const { segundosRestantes, iniciar } = useResendCooldown(props.resendCooldownSeconds);
iniciar();

const reenviar = () => {
  if (segundosRestantes.value > 0) return;
  emit('resend');
  iniciar();
};
</script>
