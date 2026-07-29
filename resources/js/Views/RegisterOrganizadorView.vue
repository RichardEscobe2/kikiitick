<template>
  <div class="min-h-[80vh] flex items-center justify-center py-10 px-4">
    <div class="max-w-2xl w-full bg-white rounded-3xl shadow-2xl shadow-slate-300/50 border border-slate-200/80 p-8 transition-all">
      
      <!-- Logo y Título -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-indigo-600 tracking-tight">KikiiTick</h1>
        <h2 class="text-xl font-bold text-gray-900 mt-2">Únete como Organizador</h2>
        <p class="text-sm text-gray-500 mt-1">
          Registra tu cuenta y los datos de tu recinto principal para comenzar a vender boletos en KikiiTick.
        </p>
      </div>

      <!-- Alertas de Error Global del Servidor -->
      <div 
        v-if="errorMsg" 
        class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-xl flex items-start gap-3 shadow-sm transition-all"
      >
        <span class="text-base">⚠️</span>
        <div class="flex-1 font-medium">{{ errorMsg }}</div>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        
        <!-- SECCIÓN 1: DATOS DEL RESPONSABLE -->
        <div>
          <h3 class="text-sm font-extrabold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
            👤 Datos del Responsable
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Nombre Completo -->
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombre completo</label>
              <input 
                v-model="form.nombre"
                type="text" 
                required
                placeholder="Ej. Ricardo Escobedo"
                @input="filtrarNombre"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
              />
              <span v-if="errorNombre" class="text-xs text-red-500 mt-1 block font-medium">
                {{ errorNombre }}
              </span>
            </div>

            <!-- Correo Electrónico -->
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Correo electrónico</label>
              <input 
                v-model="form.correo"
                type="email" 
                required
                placeholder="ejemplo@empresa.com"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
              />
            </div>
          </div>

          <!-- Contraseñas -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contraseña</label>
              <input 
                v-model="form.contrasena"
                type="password" 
                required
                placeholder="Mínimo 8 caracteres"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirmar contraseña</label>
              <input 
                v-model="form.contrasena_confirmation"
                type="password" 
                required
                placeholder="Repite la contraseña"
                :class="[
                  'w-full px-4 py-3 border rounded-xl text-sm focus:outline-none transition-all',
                  form.contrasena_confirmation 
                    ? (coincidenContrasenas 
                        ? 'bg-emerald-50/30 border-emerald-500 focus:ring-2 focus:ring-emerald-500' 
                        : 'bg-red-50/30 border-red-500 focus:ring-2 focus:ring-red-500')
                    : 'bg-gray-50 border-gray-200 focus:bg-white focus:ring-2 focus:ring-indigo-600'
                ]"
              />
            </div>
          </div>

          <!-- Feedback de coincidencia de contraseña -->
          <div v-if="form.contrasena_confirmation" class="mt-2 text-xs font-medium">
            <span v-if="coincidenContrasenas" class="text-emerald-600 flex items-center gap-1.5">
              <span>✅</span> Las contraseñas coinciden
            </span>
            <span v-else class="text-red-500 flex items-center gap-1.5">
              <span>❌</span> Las contraseñas no coinciden
            </span>
          </div>

          <!-- REQUISITOS DE CONTRASEÑA EN VIVO -->
          <div class="mt-3 p-3 bg-gray-50 rounded-xl border border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div :class="validaciones.longitud ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              {{ validaciones.longitud ? '✅' : '⚪' }} Mínimo 8 caracteres
            </div>
            <div :class="validaciones.mayuscula ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              {{ validaciones.mayuscula ? '✅' : '⚪' }} Una mayúscula (A-Z)
            </div>
            <div :class="validaciones.numero ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              {{ validaciones.numero ? '✅' : '⚪' }} Un número (0-9)
            </div>
            <div :class="validaciones.especial ? 'text-emerald-600 font-semibold' : 'text-gray-400'">
              {{ validaciones.especial ? '✅' : '⚪' }} Un carácter especial (!@#$)
            </div>
          </div>
        </div>

        <!-- SECCIÓN 2: DATOS DEL RECINTO PRINCIPAL -->
        <div>
          <h3 class="text-sm font-extrabold text-gray-900 border-b border-gray-100 pb-2 mb-4 flex items-center gap-2">
            🏛️ Datos del Recinto Principal
          </h3>

          <div class="space-y-4">
            <!-- Nombre del Recinto -->
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nombre del recinto / Teatro</label>
              <input 
                v-model="form.nombre_recinto"
                type="text" 
                required
                placeholder="Ej. Teatro Gran Recinto"
                @input="filtrarRecinto"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
              />
              <span v-if="errorRecinto" class="text-xs text-red-500 mt-1 block font-medium">
                {{ errorRecinto }}
              </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Ubicación (2 Columnas) -->
              <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Ubicación / Dirección</label>
                <input 
                  v-model="form.ubicacion_recinto"
                  type="text" 
                  required
                  placeholder="Ej. Av. Juárez 90, Centro, CDMX"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
              </div>

              <!-- Capacidad Total (1 Columna) -->
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Capacidad Máxima</label>
                <input 
                  v-model="form.capacidad_recinto"
                  type="text" 
                  inputmode="numeric"
                  required
                  placeholder="Ej. 300"
                  @input="filtrarCapacidad"
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                />
                <span v-if="errorCapacidad" class="text-xs text-red-500 mt-1 block font-medium">
                  {{ errorCapacidad }}
                </span>
              </div>
            </div>

            <!-- 🔹 ESTRUCTURA FÍSICA INICIAL (FILAS Y SILLAS POR FILA) -->
            <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 space-y-3">
              <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">📐 Distribución Física Inicial del Plano</h4>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Filas Totales (A-Z)</label>
                  <input 
                    v-model.number="form.filas_totales"
                    type="number" 
                    min="1"
                    max="26"
                    required
                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                  />
                  <span class="text-[10px] text-gray-400 mt-0.5 block">15 filas = De la A a la O</span>
                </div>

                <div>
                  <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Sillas por Fila</label>
                  <input 
                    v-model.number="form.asientos_por_fila"
                    type="number" 
                    min="1"
                    required
                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all"
                  />
                  <span class="text-[10px] text-gray-400 mt-0.5 block">Ej. 20 sillas por fila</span>
                </div>
              </div>

              <!-- Cálculo de Aforo e Indicador de Validación -->
              <div class="bg-white p-3 rounded-xl border border-indigo-100 flex justify-between items-center text-xs">
                <span class="font-medium text-gray-600">Total Sillas Matriz:</span>
                <strong :class="[ 'text-sm font-black', excedeCapacidad ? 'text-rose-600' : 'text-emerald-600' ]">
                  {{ totalSillasCalculadas }} Sillas {{ excedeCapacidad ? '❌ Excede Aforo' : '✅ Correcto' }}
                </strong>
              </div>

              <div v-if="excedeCapacidad" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium">
                ⚠️ Las sillas de la matriz ({{ totalSillasCalculadas }}) no pueden sobrepasar el aforo total permitido ({{ form.capacidad_recinto || 0 }}).
              </div>
            </div>

          </div>
        </div>

        <!-- Botón de Acción -->
        <button 
          type="submit" 
          :disabled="!todoValido || cargando || excedeCapacidad"
          class="w-full py-3.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all text-sm mt-4"
        >
          <span v-if="cargando">Procesando solicitud...</span>
          <span v-else>Enviar Solicitud de Organizador</span>
        </button>
      </form>

      <!-- Enlace a Login -->
      <p class="text-center text-xs text-gray-600 mt-6">
        ¿Ya tienes cuenta? 
        <router-link to="/login" class="text-indigo-600 font-bold hover:underline">Iniciar sesión</router-link>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const cargando = ref(false);
const errorMsg = ref('');

const errorNombre = ref('');
const errorRecinto = ref('');
const errorCapacidad = ref('');

const form = ref({
  nombre: '',
  correo: '',
  contrasena: '',
  contrasena_confirmation: '',
  nombre_recinto: '',
  ubicacion_recinto: '',
  capacidad_recinto: 300,
  filas_totales: 15,
  asientos_por_fila: 20,
  pasillos_slots_str: '5, 15',
  posicion_escenario: 'arriba'
});

const filtrarNombre = (e) => {
  const valorOriginal = e.target.value;
  const valorLimpio = valorOriginal.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
  errorNombre.value = valorOriginal !== valorLimpio ? 'El nombre solo puede contener letras y espacios.' : '';
  form.value.nombre = valorLimpio;
};

const filtrarRecinto = (e) => {
  const valorOriginal = e.target.value;
  const valorLimpio = valorOriginal.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
  errorRecinto.value = valorOriginal !== valorLimpio ? 'El nombre del recinto solo puede contener letras y espacios.' : '';
  form.value.nombre_recinto = valorLimpio;
};

const filtrarCapacidad = (e) => {
  const valorOriginal = e.target.value;
  const valorLimpio = valorOriginal.replace(/[^0-9]/g, '');
  if (valorOriginal !== valorLimpio) {
    errorCapacidad.value = 'La capacidad solo debe contener números.';
  } else if (valorLimpio !== '' && parseInt(valorLimpio, 10) <= 0) {
    errorCapacidad.value = 'La capacidad debe ser mayor a 0.';
  } else {
    errorCapacidad.value = '';
  }
  form.value.capacidad_recinto = valorLimpio;
};

const totalSillasCalculadas = computed(() => {
  return (Number(form.value.filas_totales) || 0) * (Number(form.value.asientos_por_fila) || 0);
});

const excedeCapacidad = computed(() => {
  const cap = parseInt(form.value.capacidad_recinto, 10) || 0;
  return cap > 0 && totalSillasCalculadas.value > cap;
});

const nombreValido = computed(() => {
  const n = form.value.nombre.trim();
  return n.length > 0 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(n);
});

const recintoValido = computed(() => {
  const r = form.value.nombre_recinto.trim();
  return r.length > 0 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(r);
});

const capacidadValida = computed(() => {
  const c = form.value.capacidad_recinto.toString().trim();
  return c.length > 0 && /^[0-9]+$/.test(c) && parseInt(c, 10) > 0;
});

const validaciones = computed(() => {
  const p = form.value.contrasena;
  return {
    longitud: p.length >= 8,
    mayuscula: /[A-Z]/.test(p),
    numero: /[0-9]/.test(p),
    especial: /[!@#$%^&*(),.?":{}|<>]/.test(p)
  };
});

const coincidenContrasenas = computed(() => {
  return form.value.contrasena_confirmation !== '' && 
         form.value.contrasena === form.value.contrasena_confirmation;
});

const todoValido = computed(() => {
  return nombreValido.value &&
         form.value.correo.trim() !== '' &&
         validaciones.value.longitud &&
         validaciones.value.mayuscula &&
         validaciones.value.numero &&
         validaciones.value.especial &&
         coincidenContrasenas.value &&
         recintoValido.value &&
         form.value.ubicacion_recinto.trim() !== '' &&
         capacidadValida.value &&
         form.value.filas_totales > 0 &&
         form.value.asientos_por_fila > 0;
});

const handleSubmit = async () => {
  if (!todoValido.value || excedeCapacidad.value) return;
  cargando.value = true;
  errorMsg.value = '';

  const pasillosArr = form.value.pasillos_slots_str
    ? form.value.pasillos_slots_str.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n) && n > 0)
    : [];

  const payload = {
    nombre: form.value.nombre,
    correo: form.value.correo,
    contrasena: form.value.contrasena,
    teatro_nombre: form.value.nombre_recinto,
    teatro_ubicacion: form.value.ubicacion_recinto,
    teatro_capacidad: parseInt(form.value.capacidad_recinto, 10),
    teatro_filas_totales: parseInt(form.value.filas_totales, 10),
    teatro_asientos_por_fila: parseInt(form.value.asientos_por_fila, 10),
    teatro_pasillos_slots: pasillosArr,
    teatro_posicion_escenario: form.value.posicion_escenario
  };

  try {
    const res = await fetch('/api/registro-organizador', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    if (res.status === 429) {
      errorMsg.value = 'Has superado el límite de intentos. Espera 1 minuto.';
      return;
    }

    const data = await res.json();

    if (res.ok) {
      router.push({ name: 'VerificarCodigo', query: { correo: form.value.correo } });
    } else {
      if (data.errors) {
        const primerosErrores = Object.values(data.errors).flat();
        errorMsg.value = primerosErrores[0] || 'Error de validación.';
      } else {
        errorMsg.value = data.error || data.message || 'Error al registrar el organizador.';
      }
    }
  } catch (err) {
    errorMsg.value = 'Ocurrió un problema de comunicación con el servidor.';
  } finally {
    cargando.value = false;
  }
};
</script>