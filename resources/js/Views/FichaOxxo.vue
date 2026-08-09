<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-md mx-auto">

      <div v-if="cargando" class="text-center py-20">
        <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
        <p class="text-sm font-medium text-gray-500">Generando tu ficha de pago...</p>
      </div>

      <div v-else-if="error" class="text-center py-16 bg-white rounded-3xl shadow-sm border border-rose-100 p-8">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos generar la ficha</h3>
        <p class="text-xs text-gray-500 mb-4">{{ error }}</p>
        <router-link to="/" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">Volver al Inicio</router-link>
      </div>

      <template v-else>
        <div class="text-center mb-6 print:hidden">
          <div class="text-5xl mb-2">🏪</div>
          <h1 class="text-2xl font-black text-gray-900">¡Ficha de Pago Generada!</h1>
          <p class="text-sm text-gray-500 mt-1">Paga en efectivo en cualquier tienda OXXO o 7-Eleven</p>
        </div>

        <div id="voucher-card" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-gray-900 text-white text-center py-4">
            <p class="text-xs uppercase tracking-widest text-gray-300">KikiiTick · Orden</p>
            <p class="text-lg font-black">{{ orden.folio }}</p>
          </div>

          <div class="p-6 space-y-5">
            <div class="text-center">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Código de Barras / Referencia</p>
              <svg ref="barcodeRef" class="mx-auto max-w-full"></svg>
              <p class="font-mono text-sm font-bold text-gray-700 mt-1 tracking-widest">{{ referencia }}</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 text-center">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Monto a Pagar</p>
              <p class="text-3xl font-black text-indigo-600">${{ formatearPrecio(orden.monto_total) }} MXN</p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-800 flex items-start gap-2">
              <span class="text-base">⏰</span>
              <span>
                Esta ficha vence el <strong>{{ formatearFecha(vencimiento) }}</strong>.
                Tus asientos permanecen apartados hasta entonces; si no se completa el pago, se liberarán automáticamente.
              </span>
            </div>

            <p class="text-[11px] text-gray-500 text-center leading-relaxed">
              Presenta este código en caja e indica que es un pago de servicios/entretenimiento.
              Recibirás la confirmación de tu compra por correo una vez procesado el pago.
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="descargarPdf"
          class="w-full mt-4 py-3 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold text-sm rounded-xl shadow-sm transition-all cursor-pointer print:hidden"
        >
          ⬇️ Descargar Ficha (PDF)
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import JsBarcode from 'jsbarcode';
import { axios } from '../composables/useAuth';

const route = useRoute();

const cargando = ref(true);
const error = ref('');
const orden = ref(null);
const barcodeRef = ref(null);

// 🛡️ Nota de arquitectura: esta referencia codifica el folio REAL de la orden en
// KikiiTick (Venta.id), no un código de pago en efectivo emitido por Mercado Pago —
// nuestra integración actual usa Checkout Pro (redirect a init_point), donde MP genera
// y muestra su propio código OXXO nativamente si el usuario elige esa opción ahí. Este
// código de barras es útil como referencia de la orden, pero la generación de un
// voucher OXXO real (con código MP verificable en tienda) requeriría integrar la
// Payments API de Mercado Pago con payment_method_id: 'oxxo' — no fabricamos un número
// que aparente ser eso sin un pago real detrás.
const referencia = computed(() => orden.value?.folio || '');

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  return new Date(fechaStr).toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' });
};

const vencimiento = computed(() => {
  if (!orden.value?.fecha_venta) return null;
  const fecha = new Date(orden.value.fecha_venta);
  fecha.setHours(fecha.getHours() + 24);
  return fecha.toISOString();
});

const dibujarBarcode = async () => {
  await nextTick();
  if (!barcodeRef.value || !referencia.value) return;
  JsBarcode(barcodeRef.value, referencia.value, {
    format: 'CODE128',
    height: 60,
    width: 2,
    fontSize: 0,
    margin: 0,
  });
};

const cargarOrden = async () => {
  try {
    const response = await axios.get(`/api/ventas/${route.params.ordenId}`);
    orden.value = response.data;
    await dibujarBarcode();
  } catch (err) {
    error.value = err.response
      ? (err.response.data?.message || 'No se encontró la orden solicitada.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

const descargarPdf = () => {
  window.print();
};

onMounted(() => {
  cargarOrden();
});
</script>

<style>
@media print {
  body * { visibility: hidden; }
  #voucher-card, #voucher-card * { visibility: visible; }
  #voucher-card { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
