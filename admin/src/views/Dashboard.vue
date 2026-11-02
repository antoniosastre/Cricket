<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { api } from '../api'

const estado = ref([])
const abiertas = ref([])
const fines = ref({})
const error = ref('')
let timer = null

async function load() {
  try {
    ;[estado.value, abiertas.value] = await Promise.all([api('/dashboard/estado'), api('/jornadas/abiertas')])
    for (const j of abiertas.value) {
      if (!(j.id in fines.value)) fines.value[j.id] = j.fin?.replace(' ', 'T') || ''
    }
    error.value = ''
  } catch (e) {
    error.value = e.message
  }
}

async function resolver(j, descartar) {
  if (descartar && !confirm('¿Descartar esta jornada?')) return
  try {
    if (descartar) await api(`/jornadas/${j.id}`, { method: 'DELETE' })
    else await api(`/jornadas/${j.id}`, { method: 'PATCH', body: { fin: fines.value[j.id] } })
    await load()
  } catch (e) { error.value = e.message }
}

function elapsed(inicio) {
  if (!inicio) return ''
  const mins = Math.max(0, Math.round((Date.now() - new Date(inicio.replace(' ', 'T') + 'Z').getTime()) / 60000))
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${h}h ${String(m).padStart(2, '0')}m`
}

onMounted(() => {
  load()
  timer = setInterval(load, 5000) // polling cada 5s
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div class="container">
    <h2>Estado de los trabajadores</h2>
    <p v-if="error" class="error">{{ error }}</p>
    <div class="card" v-for="t in estado" :key="t.usuario_id"
         style="display:flex; align-items:center; gap:1rem;">
      <span :class="['badge', t.activo ? 'on' : 'off']">
        {{ t.activo ? '● Trabajando' : '○ Parado' }}
      </span>
      <strong>{{ t.nombre }}</strong>
      <span class="spacer" style="flex:1"></span>
      <template v-if="t.activo">
        <span class="muted">{{ t.instalacion }}</span>
        <strong>{{ elapsed(t.inicio) }}</strong>
      </template>
      <span v-else class="muted">Sin temporizador</span>
    </div>
    <div v-if="!estado.length" class="empty">No hay trabajadores dados de alta.</div>
    <section aria-labelledby="abiertas">
      <h2 id="abiertas">Jornadas sin confirmar</h2>
      <p v-if="!abiertas.length" class="muted">Todas las jornadas están resueltas.</p>
      <form v-for="j in abiertas" :key="j.id" class="card row" @submit.prevent="resolver(j, false)">
        <div><strong>{{ j.trabajador }}</strong><p>{{ j.instalacion }} · Inicio {{ j.inicio }}</p></div>
        <label><span>Fin de la jornada</span><input type="datetime-local" step="1" v-model="fines[j.id]" required /></label>
        <button type="submit">Cerrar</button>
        <button type="button" class="danger" @click="resolver(j, true)">Descartar</button>
      </form>
    </section>
  </div>
</template>
