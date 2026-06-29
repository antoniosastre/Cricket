<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { cachePut, cacheGet, queueJornada } from '../db'
import { flush, refreshCount } from '../sync'
import {
  session, segmentoActivo, iniciarTramo, pararTramo,
  minutosTotales, anadirMaterial, quitarMaterial,
} from '../session'

const router = useRouter()
const ahora = ref(Date.now())
const catalogo = ref([])
const modal = ref(false)
let tick = null

const activo = computed(() => !!segmentoActivo())
const display = computed(() => {
  ahora.value // dependencia para refrescar cada segundo
  const total = Math.floor(minutosTotales() * 60)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  return [h, m, s].map(n => String(n).padStart(2, '0')).join(':')
})

onMounted(async () => {
  tick = setInterval(() => (ahora.value = Date.now()), 1000)
  try {
    catalogo.value = await api('/catalogo')
    await cachePut('catalogo', catalogo.value)
  } catch {
    catalogo.value = (await cacheGet('catalogo')) || []
  }
})
onUnmounted(() => clearInterval(tick))

// Encola el segmento actual como jornada y sincroniza (heartbeat para el dashboard).
async function heartbeat(estado) {
  const s = segmentoActivo() || session.segmentos[session.segmentos.length - 1]
  if (!s) return
  await queueJornada({
    uuid: s.uuid,
    instalacion_id: session.instalacion_id,
    inicio: s.inicio,
    fin: s.fin,
    estado,
  })
  await refreshCount()
  flush()
}

async function toggleTimer() {
  if (activo.value) {
    pararTramo()
    await heartbeat('en_curso') // tramo cerrado, pero aun no confirmado
  } else {
    iniciarTramo()
    await heartbeat('en_curso')
  }
}

// --- Modal de material ---
const mat = ref(nuevoMat())
function nuevoMat() {
  return { catalogo_id: '', descripcion: '', cantidad: 1, unidad: 'ud', precio_unit: '', origen: 'empresa' }
}
function elegirCatalogo() {
  const c = catalogo.value.find(x => x.id === Number(mat.value.catalogo_id))
  if (c) {
    mat.value.descripcion = c.descripcion
    mat.value.unidad = c.unidad
    mat.value.precio_unit = c.precio ?? ''
  }
}
function guardarMaterial() {
  if (!mat.value.descripcion.trim()) return
  anadirMaterial({
    catalogo_id: mat.value.catalogo_id || null,
    descripcion: mat.value.descripcion,
    cantidad: Number(mat.value.cantidad) || 1,
    unidad: mat.value.unidad || 'ud',
    precio_unit: mat.value.precio_unit === '' ? null : Number(mat.value.precio_unit),
    origen: mat.value.origen,
  })
  mat.value = nuevoMat()
  modal.value = false
}

function finalizar() {
  if (activo.value) pararTramo()
  router.push('/resumen')
}
</script>

<template>
  <div class="screen">
    <div class="panel center">
      <div class="muted">{{ session.instalacion_nombre }}</div>
      <div :class="['timer', activo ? 'run' : '']">{{ display }}</div>
      <button :class="activo ? 'danger' : 'ok'" @click="toggleTimer">
        {{ activo ? '■ Parar' : '▶ Iniciar' }}
      </button>
    </div>

    <div class="panel">
      <div class="row" style="justify-content:space-between;">
        <h3>Materiales ({{ session.materiales.length }})</h3>
        <button class="sm" @click="modal = true">+ Añadir</button>
      </div>
      <div v-if="!session.materiales.length" class="muted center" style="padding:1rem 0;">
        Aún no has añadido materiales.
      </div>
      <div v-for="m in session.materiales" :key="m.uuid" class="list-item">
        <span :class="['tag', m.origen]">{{ m.origen }}</span>
        <div style="flex:1">
          <div>{{ m.descripcion }}</div>
          <div class="muted" style="font-size:.82rem;">{{ m.cantidad }} {{ m.unidad }}</div>
        </div>
        <button class="sm sec" @click="quitarMaterial(m.uuid)">✕</button>
      </div>
    </div>

    <button class="warn" @click="finalizar">Finalizar instalación</button>

    <!-- Modal añadir material -->
    <div v-if="modal" class="modal-bg" @click.self="modal = false">
      <div class="modal">
        <h3>Añadir material</h3>
        <label>
          <span>Del catálogo</span>
          <select v-model="mat.catalogo_id" @change="elegirCatalogo">
            <option value="">— escribir manualmente —</option>
            <option v-for="c in catalogo" :key="c.id" :value="c.id">{{ c.descripcion }}</option>
          </select>
        </label>
        <label><span>Descripción</span><input v-model="mat.descripcion" /></label>
        <div class="row">
          <label style="flex:1"><span>Cantidad</span><input v-model="mat.cantidad" type="number" step="0.01" inputmode="decimal" /></label>
          <label style="flex:1"><span>Unidad</span><input v-model="mat.unidad" /></label>
        </div>
        <label>
          <span>Aportado por</span>
          <select v-model="mat.origen">
            <option value="empresa">Empresa instaladora</option>
            <option value="cliente">Cliente</option>
          </select>
        </label>
        <button class="ok" @click="guardarMaterial">Añadir</button>
        <button class="sec" style="margin-top:.6rem;" @click="modal = false">Cancelar</button>
      </div>
    </div>
  </div>
</template>
