<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { queueJornada, queueMaterial } from '../db'
import { flush, refreshCount } from '../sync'
import { session, minutosTotales, limpiarSesion } from '../session'

const router = useRouter()
const guardando = ref(false)

const horas = computed(() => (minutosTotales() / 60).toFixed(2))

function fmtHora(iso) {
  return iso ? new Date(iso).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : '—'
}
function durSeg(s) {
  const fin = s.fin ? new Date(s.fin).getTime() : Date.now()
  const min = Math.round((fin - new Date(s.inicio).getTime()) / 60000)
  return `${Math.floor(min / 60)}h ${String(min % 60).padStart(2, '0')}m`
}

async function confirmar() {
  guardando.value = true
  // Encola los tramos como jornadas confirmadas y los materiales.
  for (const s of session.segmentos) {
    if (!s.fin) s.fin = new Date().toISOString()
    await queueJornada({
      uuid: s.uuid,
      instalacion_id: session.instalacion_id,
      inicio: s.inicio,
      fin: s.fin,
      estado: 'confirmada',
    })
  }
  for (const m of session.materiales) {
    await queueMaterial({ ...m, instalacion_id: session.instalacion_id })
  }
  await refreshCount()
  flush() // se sincroniza ya si hay conexion; si no, queda en cola
  limpiarSesion()
  guardando.value = false
  alert('Trabajo guardado. Se subirá cuando haya conexión si ahora no la hay.')
  router.push('/')
}

async function salirSinGuardar() {
  if (!confirm('Se perderán las horas y materiales de esta sesión. ¿Salir sin guardar?')) return
  // Descarta en el servidor cualquier tramo que ya se hubiera enviado (heartbeat).
  for (const s of session.segmentos) {
    await queueJornada({
      uuid: s.uuid,
      instalacion_id: session.instalacion_id,
      inicio: s.inicio,
      fin: s.fin || new Date().toISOString(),
      estado: 'descartada',
    })
  }
  await refreshCount()
  flush()
  limpiarSesion()
  router.push('/')
}
</script>

<template>
  <div class="screen">
    <h2>Resumen</h2>
    <div class="panel">
      <div class="muted">Instalación</div>
      <h3>{{ session.instalacion_nombre }}</h3>
    </div>

    <div class="panel">
      <div class="row" style="justify-content:space-between;">
        <h3>Horas</h3>
        <strong>{{ horas }} h</strong>
      </div>
      <div v-for="s in session.segmentos" :key="s.uuid" class="list-item">
        <div style="flex:1">{{ fmtHora(s.inicio) }} – {{ fmtHora(s.fin) }}</div>
        <strong>{{ durSeg(s) }}</strong>
      </div>
      <div v-if="!session.segmentos.length" class="muted">Sin tiempo registrado.</div>
    </div>

    <div class="panel">
      <h3>Materiales ({{ session.materiales.length }})</h3>
      <div v-for="m in session.materiales" :key="m.uuid" class="list-item">
        <span :class="['tag', m.origen]">{{ m.origen }}</span>
        <div style="flex:1">{{ m.descripcion }}</div>
        <span class="muted">{{ m.cantidad }} {{ m.unidad }}</span>
      </div>
      <div v-if="!session.materiales.length" class="muted">Sin materiales.</div>
    </div>

    <button class="ok" :disabled="guardando" @click="confirmar">Confirmar y subir</button>
    <button class="sec" @click="router.push('/sesion')">Volver</button>
    <button class="danger" @click="salirSinGuardar">Salir sin guardar</button>
  </div>
</template>
