<template>
  <div class="min-h-[75vh] flex items-center justify-center py-10 px-4">
    <VerificationCard
      v-model:code="codigo"
      title="Verifica tu cuenta"
      subtitle="Hemos enviado un código de 6 dígitos a"
      :email="correo"
      button-text="Validar y Crear Cuenta"
      icon-type="mail"
      action-type="verify_account"
      :loading="cargando"
      :error-msg="errorMsg"
      :disable-submit="codigo.length !== 6"
      @submit="verificarCodigo"
      @resend="reenviarCodigo"
    >
      <!-- Estado de éxito: reemplaza el alert() nativo por un panel en línea,
           consistente con el patrón que ya usa ResetPasswordView.vue. -->
      <div v-if="exito" class="mt-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl text-center space-y-1">
        <p class="font-bold text-sm">🎉 ¡Cuenta activada con éxito!</p>
        <p class="text-emerald-600">Redirigiendo al inicio de sesión...</p>
      </div>
    </VerificationCard>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { axios } from '../composables/useAuth';
import VerificationCard from '../Components/VerificationCard.vue';

const route = useRoute();
const router = useRouter();

const correo = ref('');
const codigo = ref('');
const cargando = ref(false);
const errorMsg = ref('');
const exito = ref(false);
let redirectTimer = null;

onMounted(() => {
  correo.value = route.query.correo || '';
});

const verificarCodigo = async () => {
  if (codigo.value.length !== 6) return;

  cargando.value = true;
  errorMsg.value = '';

  try {
    await axios.post('/api/verificar-codigo', { correo: correo.value, codigo: codigo.value });
    exito.value = true;
    redirectTimer = setTimeout(() => router.push('/login'), 1800);
  } catch (e) {
    // 🐛 El backend siempre responde con la clave 'message' (nunca 'error') —
    // con e.response?.data?.error el texto real (código incorrecto vs.
    // expirado) nunca llegaba a mostrarse, siempre caía al genérico.
    errorMsg.value = e.response?.data?.message || 'Código inválido.';
  } finally {
    cargando.value = false;
  }
};

const reenviarCodigo = async () => {
  errorMsg.value = '';
  try {
    await axios.post('/api/enviar-codigo', { correo: correo.value });
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'No se pudo reenviar el código.';
  }
};

onUnmounted(() => {
  if (redirectTimer) clearTimeout(redirectTimer);
});
</script>
