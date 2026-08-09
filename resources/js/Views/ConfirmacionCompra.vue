<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-2xl mx-auto">

      <!-- Cargando -->
      <div v-if="cargando" class="text-center py-20">
        <div class="inline-block animate-spin text-4xl mb-3">🎟️</div>
        <p class="text-sm font-medium text-gray-500">Buscando tu orden...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-16 bg-white rounded-3xl shadow-sm border border-rose-100 p-8">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">No pudimos encontrar esta orden</h3>
        <p class="text-xs text-gray-500 mb-4">{{ error }}</p>
        <router-link to="/" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">Volver al Inicio</router-link>
      </div>

      <template v-else>
        <!-- BANNER: PAGADO -->
        <div v-if="orden.estatus_pago === 'pagado'" class="text-center mb-6 print:hidden">
          <div class="text-5xl mb-2">🎉</div>
          <h1 class="text-2xl font-black text-gray-900">¡Compra Confirmada!</h1>
          <p class="text-sm text-gray-500 mt-1">
            Hemos enviado el comprobante a tu correo electrónico. Orden #{{ orden.folio }}
          </p>
        </div>

        <!-- BANNER: PENDIENTE -->
        <div v-else-if="orden.estatus_pago === 'pendiente'" class="text-center mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-6">
          <div class="text-4xl mb-2">⏳</div>
          <h1 class="text-xl font-black text-gray-900">Estamos confirmando tu pago</h1>
          <p class="text-sm text-gray-500 mt-1">
            Orden #{{ orden.folio }} ·
            <span v-if="orden.metodo_pago === 'oxxo'">Pagando en OXXO/7-Eleven, la confirmación puede tardar hasta 24 horas.</span>
            <span v-else>La confirmación de tu pago está en proceso.</span>
            Esta página se actualizará sola cuando se confirme.
          </p>
        </div>

        <!-- BANNER: FALLIDO -->
        <div v-else class="text-center mb-6 bg-rose-50 border border-rose-200 rounded-2xl p-6">
          <div class="text-4xl mb-2">❌</div>
          <h1 class="text-xl font-black text-gray-900">El pago no se pudo procesar</h1>
          <p class="text-sm text-gray-500 mt-1 mb-4">Orden #{{ orden.folio }} · No se realizó ningún cargo.</p>
          <router-link
            v-if="orden.evento"
            :to="`/evento/${orden.evento.id}`"
            class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl"
          >
            Intentar de nuevo
          </router-link>
        </div>

        <!-- FICHA DE PAGO OXXO: solo mientras esté pendiente y el método sea efectivo.
             Sin QR de entrada ni descarga de boleto — el acceso solo se habilita tras
             confirmar el pago de verdad (RN-11). -->
        <div
          v-if="orden.estatus_pago === 'pendiente' && orden.metodo_pago === 'oxxo'"
          class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-6"
        >
          <div class="bg-gray-900 text-white text-center py-4">
            <p class="text-xs uppercase tracking-widest text-gray-300">Ficha de Pago OXXO</p>
            <p class="text-lg font-black">{{ orden.folio }}</p>
          </div>

          <div class="p-6 space-y-5">
            <div class="text-center">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Referencia de pago</p>
              <svg ref="barcodeRef" class="mx-auto max-w-full"></svg>
              <p class="font-mono text-sm font-bold text-gray-700 mt-1 tracking-widest">{{ orden.folio }}</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 text-center">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Monto a Pagar</p>
              <p class="text-3xl font-black text-indigo-600">${{ formatearPrecio(orden.monto_total) }} MXN</p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-800 flex items-start gap-2">
              <span class="text-base">⏰</span>
              <span>
                Esta ficha vence el <strong>{{ formatearFecha(vencimientoOxxo) }}</strong>.
                Tus asientos permanecen apartados hasta entonces; si no se completa el pago, se liberarán automáticamente.
              </span>
            </div>

            <ol class="text-[11px] text-gray-600 leading-relaxed space-y-1.5 list-decimal list-inside">
              <li>Acude a cualquier tienda OXXO o 7-Eleven.</li>
              <li>Indica en caja que harás un pago de servicios/entretenimiento.</li>
              <li>Muestra o dicta la referencia <strong class="font-mono">{{ orden.folio }}</strong> anterior.</li>
              <li>Paga el monto exacto en efectivo y conserva tu comprobante.</li>
              <li>Recibirás la confirmación de tu compra por correo una vez procesado el pago.</li>
            </ol>

            <router-link
              :to="`/ficha-oxxo/${orden.id}`"
              class="block text-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl"
            >
              🏪 Abrir ficha completa / imprimir
            </router-link>

            <!-- 🧪 Solo visible cuando el backend confirma que NO estamos en producción
                 (orden.entorno_pruebas) — el endpoint vuelve a verificar esto por su
                 cuenta antes de hacer nada, este flag solo controla si se muestra el
                 botón. Evita depender del sandbox real de Mercado Pago en QA/demo. -->
            <div v-if="orden.entorno_pruebas" class="pt-4 border-t border-dashed border-gray-200">
              <p class="text-[10px] text-gray-400 text-center mb-2 uppercase tracking-wider">Solo entorno de pruebas</p>
              <button
                type="button"
                @click="simularPago"
                :disabled="simulando"
                class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-bold text-xs rounded-xl transition-all cursor-pointer disabled:cursor-not-allowed"
              >
                {{ simulando ? '⏳ Simulando...' : '✅ Simular Pago Acreditado (Sandbox)' }}
              </button>
              <p v-if="errorSimulacion" class="text-[11px] text-rose-600 mt-2">{{ errorSimulacion }}</p>
            </div>
          </div>
        </div>

        <!-- TARJETA DEL BOLETO: solo con pago confirmado (RN-11) — antes de eso el QR
             de entrada no debe estar disponible. -->
        <div v-if="orden.estatus_pago === 'pagado'" id="ticket-card" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-6">
          <div class="h-32 bg-gray-900 relative">
            <img v-if="orden.evento?.imagen_url" :src="orden.evento.imagen_url" class="w-full h-full object-cover opacity-60" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            <div class="absolute bottom-3 left-5 right-5 text-white">
              <h2 class="text-lg font-black leading-tight">{{ orden.evento?.titulo }}</h2>
              <p class="text-xs text-gray-300">
                📅 {{ formatearFecha(orden.evento?.fecha_hora) }} · 📍 {{ orden.evento?.teatro?.nombre }}
              </p>
            </div>
          </div>

          <div class="p-5 divide-y divide-gray-100">
            <div v-for="boleto in orden.boletos" :key="boleto.numero_control" class="py-4 flex items-center gap-4 first:pt-0 last:pb-0">
              <img
                v-if="qrDataUrls[boleto.numero_control]"
                :src="qrDataUrls[boleto.numero_control]"
                :alt="`Código QR ${boleto.numero_control}`"
                class="w-20 h-20 rounded-lg border border-gray-100 shrink-0"
              />
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-gray-900">{{ boleto.seccion_pasillo }}</p>
                <p class="text-xs text-gray-500">Fila {{ boleto.fila_palco }} · Asiento #{{ boleto.numero_asiento }}</p>
                <p class="text-[10px] font-mono text-gray-400 mt-0.5">Folio: {{ boleto.numero_control }}</p>
              </div>
              <span
                class="text-[10px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap"
                :class="boleto.estatus === 'validado' ? 'bg-gray-100 text-gray-500' : 'bg-emerald-50 text-emerald-700'"
              >
                {{ boleto.estatus === 'validado' ? 'Ya usado' : 'Vigente' }}
              </span>
            </div>
          </div>

          <div class="bg-gray-50 p-5 text-xs space-y-1.5">
            <div class="flex justify-between text-gray-600">
              <span>Subtotal boletos</span>
              <span class="font-semibold">${{ formatearPrecio(orden.monto_neto) }} MXN</span>
            </div>
            <div class="flex justify-between text-gray-600">
              <span>Cargo por servicio</span>
              <span class="font-semibold">${{ formatearPrecio(orden.total_comisiones) }} MXN</span>
            </div>
            <div class="flex justify-between text-sm font-black text-gray-900 pt-1.5 border-t border-gray-200">
              <span>Total pagado</span>
              <span class="text-indigo-600">${{ formatearPrecio(orden.monto_total) }} MXN</span>
            </div>
          </div>
        </div>

        <!-- RESUMEN SIN BOLETOS: mientras el pago no esté confirmado, mostramos el
             evento/monto pero sin QRs de entrada. -->
        <div v-else-if="orden.evento" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-6 opacity-80">
          <div class="h-24 bg-gray-900 relative">
            <img v-if="orden.evento?.imagen_url" :src="orden.evento.imagen_url" class="w-full h-full object-cover opacity-40" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            <div class="absolute bottom-3 left-5 right-5 text-white">
              <h2 class="text-base font-black leading-tight">{{ orden.evento?.titulo }}</h2>
              <p class="text-xs text-gray-300">
                📅 {{ formatearFecha(orden.evento?.fecha_hora) }} · 📍 {{ orden.evento?.teatro?.nombre }}
              </p>
            </div>
          </div>
          <div class="p-4 text-xs text-gray-500">
            {{ orden.boletos?.length || 0 }} boleto(s) · Total ${{ formatearPrecio(orden.monto_total) }} MXN — se mostrarán con su código QR aquí una vez confirmado el pago.
          </div>
        </div>

        <!-- ACCIONES -->
        <div class="flex flex-col sm:flex-row gap-3 print:hidden">
          <template v-if="orden.estatus_pago === 'pagado'">
            <button
              type="button"
              @click="descargarPdf"
              class="flex-1 py-3 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold text-sm rounded-xl shadow-sm transition-all cursor-pointer"
            >
              ⬇️ Descargar Boleto (PDF)
            </button>
            <router-link
              :to="{ name: 'MisBoletos' }"
              class="flex-1 text-center py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition-all"
            >
              🎫 Ver Mis Boletos
            </router-link>
          </template>
          <router-link
            v-else
            to="/"
            class="flex-1 text-center py-3 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold text-sm rounded-xl shadow-sm transition-all"
          >
            Volver al Inicio
          </router-link>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import QRCode from 'qrcode';
import JsBarcode from 'jsbarcode';
import { axios } from '../composables/useAuth';

const route = useRoute();

const cargando = ref(true);
const error = ref('');
const orden = ref(null);
const qrDataUrls = ref({});
const barcodeRef = ref(null);
const simulando = ref(false);
const errorSimulacion = ref('');

let pollingTimer = null;

// La ficha OXXO vence 24h después de creada la orden (mismo cálculo que
// FichaOxxo.vue) — coincide con la ventana de reserva extendida que
// CompraService::procesarCompra() aplica a los asientos cuando metodo_pago==='oxxo'.
const vencimientoOxxo = computed(() => {
  if (!orden.value?.fecha_venta) return null;
  const fecha = new Date(orden.value.fecha_venta);
  fecha.setHours(fecha.getHours() + 24);
  return fecha.toISOString();
});

const formatearPrecio = (val) => {
  const num = Number(val);
  return isNaN(num) ? '0.00' : num.toFixed(2);
};

const formatearFecha = (fechaStr) => {
  if (!fechaStr) return '';
  return new Date(fechaStr).toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' });
};

// RF-12: un código QR único por boleto (Acceso.token_qr), generado 100% en el
// navegador con la librería `qrcode` — el token nunca se envía a ningún servicio
// externo de terceros, solo se renderiza localmente como imagen.
const generarQrCodes = async () => {
  if (!orden.value?.boletos) return;
  for (const boleto of orden.value.boletos) {
    try {
      qrDataUrls.value[boleto.numero_control] = await QRCode.toDataURL(boleto.token_qr, {
        width: 160,
        margin: 1,
        color: { dark: '#1e1b4b', light: '#ffffff' },
      });
    } catch {
      // Si falla la generación de un QR puntual, el resto del boleto sigue visible.
    }
  }
};

// Referencia codificada en CODE128: el folio REAL de la orden en KikiiTick, no un
// voucher OXXO emitido por Mercado Pago (esa integración —Payments API con
// payment_method_id: 'oxxo'— no está implementada; ver FichaOxxo.vue).
const dibujarBarcode = async () => {
  await nextTick();
  if (!barcodeRef.value || !orden.value?.folio) return;
  JsBarcode(barcodeRef.value, orden.value.folio, {
    format: 'CODE128',
    height: 50,
    width: 2,
    fontSize: 0,
    margin: 0,
  });
};

const simularPago = async () => {
  simulando.value = true;
  errorSimulacion.value = '';
  try {
    await axios.post(`/api/ventas/${route.params.ordenId}/simular-pago`);
    await cargarOrden();
  } catch (err) {
    errorSimulacion.value = err.response
      ? (err.response.data?.message || 'No se pudo simular el pago.')
      : 'Error de conexión con el servidor.';
  } finally {
    simulando.value = false;
  }
};

const cargarOrden = async () => {
  try {
    // 🛡️ Mercado Pago añade payment_id/collection_id/status a esta URL al volver del
    // checkout — se reenvían tal cual al backend, que SOLO usa el id como puntero
    // para re-verificar el pago contra la API de Mercado Pago (nunca confía en el
    // "status" de la propia URL). Ver CompraController::reconciliarPagoPendiente().
    const response = await axios.get(`/api/ventas/${route.params.ordenId}`, { params: route.query });
    orden.value = response.data;
    error.value = '';
    if (orden.value.estatus_pago === 'pagado') {
      await generarQrCodes();
    } else if (orden.value.estatus_pago === 'pendiente' && orden.value.metodo_pago === 'oxxo') {
      await dibujarBarcode();
    }

    // Mientras el pago siga 'pendiente' (típico de OXXO, que confirma horas después
    // vía webhook), refrescamos la orden cada 15s para reflejar la confirmación sin
    // que el usuario tenga que recargar la página manualmente.
    if (orden.value.estatus_pago === 'pendiente' && !pollingTimer) {
      pollingTimer = setInterval(cargarOrden, 15000);
    } else if (orden.value.estatus_pago !== 'pendiente' && pollingTimer) {
      clearInterval(pollingTimer);
      pollingTimer = null;
    }
  } catch (err) {
    error.value = err.response
      ? (err.response.data?.message || 'No se encontró la orden solicitada.')
      : 'Error de conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

// 🖨️ Sin backend de generación de PDF en este alcance: se usa la impresión nativa
// del navegador (con estilos @print) como "Descargar PDF" — cero dependencias
// nuevas, funciona en cualquier navegador vía "Guardar como PDF".
const descargarPdf = () => {
  window.print();
};

onMounted(() => {
  cargarOrden();
});

onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});
</script>

<style>
@media print {
  body * { visibility: hidden; }
  #ticket-card, #ticket-card * { visibility: visible; }
  #ticket-card { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
