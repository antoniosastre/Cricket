<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api, download } from '../api'

const lista = ref([])
const error = ref('')
const open = reactive({})        // instalacion_id -> bool (desplegado)
const detalle = reactive({})     // instalacion_id -> { horas, materiales }
const checks = reactive({})      // instalacion_id -> { horas:Set, mat:Set }
const busy = reactive({})

async function load() {
  try {
    lista.value = await api('/trabajos')
  } catch (e) { error.value = e.message }
}

async function toggle(inst) {
  open[inst.id] = !open[inst.id]
  if (open[inst.id] && !detalle[inst.id]) {
    detalle[inst.id] = await api(`/trabajos/${inst.id}`)
    checks[inst.id] = { horas: reactive(new Set()), mat: reactive(new Set()) }
  }
}

function toggleCheck(instId, tipo, id) {
  const set = checks[instId][tipo]
  set.has(id) ? set.delete(id) : set.add(id)
}

function totalHoras(instId) {
  const d = detalle[instId]; if (!d) return 0
  const set = checks[instId].horas
  return d.horas.filter(h => set.has(h.id)).reduce((s, h) => s + h.horas, 0)
}
function totalMat(instId) {
  return checks[instId] ? checks[instId].mat.size : 0
}
function materialesPorOrigen(instId, origen) {
  const d = detalle[instId]; if (!d) return []
  return d.materiales.filter(m => m.origen === origen)
}

async function facturar(instId) {
  const c = checks[instId]
  if (!c.horas.size && !c.mat.size) { alert('Marca al menos una linea'); return }
  if (!confirm('¿Marcar las lineas seleccionadas como facturadas?')) return
  busy[instId] = true
  try {
    const res = await api('/facturacion', {
      method: 'POST',
      body: { jornadas: [...c.horas], materiales: [...c.mat] },
    })
    if (confirm('Facturado. ¿Descargar el CSV del lote?')) {
      await download(`/facturacion/${res.lote_id}/export.csv`, `facturacion_lote_${res.lote_id}.csv`)
    }
    delete detalle[instId]
    open[instId] = false
    await load()
  } catch (e) { alert(e.message) }
  finally { busy[instId] = false }
}
function fmtFecha(s) { return s ? s.substring(0, 10) : '' }
onMounted(load)
</script>

<template>
  <div class="container">
    <h2>Trabajos pendientes de facturar</h2>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="!lista.length" class="empty">No hay instalaciones con elementos pendientes.</div>

    <div class="card" v-for="inst in lista" :key="inst.id">
      <div style="display:flex; align-items:center; gap:1rem; cursor:pointer;" @click="toggle(inst)">
        <strong>{{ inst.nombre }}</strong>
        <span class="muted" v-if="inst.cliente">· {{ inst.cliente }}</span>
        <span class="spacer" style="flex:1"></span>
        <span class="muted">{{ inst.total_horas }} h · {{ inst.total_mat }} materiales</span>
        <span style="font-size:1.2rem;">{{ open[inst.id] ? '▾' : '▸' }}</span>
      </div>

      <div v-if="open[inst.id] && detalle[inst.id]" style="margin-top:1rem;">
        <!-- Bloque horas -->
        <div class="section-title">Horas</div>
        <table>
          <thead><tr><th></th><th>Fecha</th><th class="right">Horas</th><th>Trabajador</th></tr></thead>
          <tbody>
            <tr v-for="h in detalle[inst.id].horas" :key="h.id">
              <td><input type="checkbox" style="width:auto"
                         :checked="checks[inst.id].horas.has(h.id)"
                         @change="toggleCheck(inst.id, 'horas', h.id)" /></td>
              <td>{{ fmtFecha(h.fecha) }}</td>
              <td class="right">{{ h.horas }}</td>
              <td>{{ h.trabajador }}</td>
            </tr>
            <tr v-if="!detalle[inst.id].horas.length"><td colspan="4" class="muted">Sin horas.</td></tr>
          </tbody>
        </table>

        <!-- Bloque materiales -->
        <div class="section-title">Materiales (empresa)</div>
        <table>
          <thead><tr><th></th><th>Fecha</th><th>Descripcion</th><th class="right">Cant.</th><th>Trabajador</th></tr></thead>
          <tbody>
            <tr v-for="m in materialesPorOrigen(inst.id, 'empresa')" :key="m.id">
              <td><input type="checkbox" style="width:auto"
                         :checked="checks[inst.id].mat.has(m.id)"
                         @change="toggleCheck(inst.id, 'mat', m.id)" /></td>
              <td>{{ fmtFecha(m.fecha) }}</td>
              <td>{{ m.descripcion }}</td>
              <td class="right">{{ m.cantidad }} {{ m.unidad }}</td>
              <td>{{ m.trabajador }}</td>
            </tr>
            <tr v-if="!materialesPorOrigen(inst.id,'empresa').length"><td colspan="5" class="muted">Sin materiales de empresa.</td></tr>
          </tbody>
        </table>

        <div class="section-title">Materiales (cliente)</div>
        <table>
          <thead><tr><th></th><th>Fecha</th><th>Descripcion</th><th class="right">Cant.</th><th>Trabajador</th></tr></thead>
          <tbody>
            <tr v-for="m in materialesPorOrigen(inst.id, 'cliente')" :key="m.id">
              <td><input type="checkbox" style="width:auto"
                         :checked="checks[inst.id].mat.has(m.id)"
                         @change="toggleCheck(inst.id, 'mat', m.id)" /></td>
              <td>{{ fmtFecha(m.fecha) }}</td>
              <td>{{ m.descripcion }}</td>
              <td class="right">{{ m.cantidad }} {{ m.unidad }}</td>
              <td>{{ m.trabajador }}</td>
            </tr>
            <tr v-if="!materialesPorOrigen(inst.id,'cliente').length"><td colspan="5" class="muted">Sin materiales de cliente.</td></tr>
          </tbody>
        </table>

        <!-- Totales y facturar -->
        <div class="totals">
          <span>Total horas: <strong>{{ totalHoras(inst.id).toFixed(2) }}</strong></span>
          <span>Materiales: <strong>{{ totalMat(inst.id) }}</strong></span>
          <button class="ok" :disabled="busy[inst.id]" @click="facturar(inst.id)">
            Marcar como facturado
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
