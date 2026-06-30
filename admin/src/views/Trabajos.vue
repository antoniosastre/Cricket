<script setup>
import { ref, reactive, onMounted } from 'vue'
import { api, download } from '../api'

const lista = ref([])
const error = ref('')
const open = reactive({})        // instalacion_id -> bool (desplegado)
const detalle = reactive({})     // instalacion_id -> { horas, materiales }
const checks = reactive({})      // instalacion_id -> { horas:Set, mat:Set }
const busy = reactive({})
const editing = ref(null)        // { tipo, instId, id, form }

async function load() {
  try {
    lista.value = await api('/trabajos')
  } catch (e) { error.value = e.message }
}

async function reloadDetalle(instId) {
  detalle[instId] = await api(`/trabajos/${instId}`)
  await load()
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

// --- Edicion de lineas ---
function toLocal(s) { return s ? s.replace(' ', 'T').substring(0, 16) : '' }

function editarHora(instId, h) {
  editing.value = {
    tipo: 'hora', instId, id: h.id,
    form: { inicio: toLocal(h.fecha), fin: toLocal(h.fin) },
  }
}
function editarMaterial(instId, m) {
  editing.value = {
    tipo: 'material', instId, id: m.id,
    form: {
      fecha: m.fecha ? m.fecha.substring(0, 10) : '',
      descripcion: m.descripcion,
      cantidad: m.cantidad,
      unidad: m.unidad,
      precio_unit: m.precio_unit ?? '',
      origen: m.origen,
    },
  }
}

async function guardarEdicion() {
  const e = editing.value
  const path = e.tipo === 'hora' ? `/jornadas/${e.id}` : `/materiales/${e.id}`
  try {
    await api(path, { method: 'PATCH', body: e.form })
    const instId = e.instId
    editing.value = null
    await reloadDetalle(instId)
  } catch (err) { alert(err.message) }
}

async function borrarLinea(tipo, instId, id) {
  if (!confirm('¿Eliminar esta linea? No se puede deshacer.')) return
  const path = tipo === 'hora' ? `/jornadas/${id}` : `/materiales/${id}`
  try {
    await api(path, { method: 'DELETE' })
    checks[instId][tipo === 'hora' ? 'horas' : 'mat'].delete(id)
    await reloadDetalle(instId)
  } catch (err) { alert(err.message) }
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
          <thead><tr><th></th><th>Fecha</th><th class="right">Horas</th><th>Trabajador</th><th></th></tr></thead>
          <tbody>
            <tr v-for="h in detalle[inst.id].horas" :key="h.id">
              <td><input type="checkbox" style="width:auto"
                         :checked="checks[inst.id].horas.has(h.id)"
                         @change="toggleCheck(inst.id, 'horas', h.id)" /></td>
              <td>{{ fmtFecha(h.fecha) }}</td>
              <td class="right">{{ h.horas }}</td>
              <td>{{ h.trabajador }}</td>
              <td class="right nowrap">
                <button class="ghost sm" @click="editarHora(inst.id, h)">✎</button>
                <button class="danger sm" @click="borrarLinea('hora', inst.id, h.id)">✕</button>
              </td>
            </tr>
            <tr v-if="!detalle[inst.id].horas.length"><td colspan="5" class="muted">Sin horas.</td></tr>
          </tbody>
        </table>

        <!-- Bloque materiales -->
        <template v-for="origen in ['empresa', 'cliente']" :key="origen">
          <div class="section-title">Materiales ({{ origen }})</div>
          <table>
            <thead><tr><th></th><th>Fecha</th><th>Descripcion</th><th class="right">Cant.</th><th>Trabajador</th><th></th></tr></thead>
            <tbody>
              <tr v-for="m in materialesPorOrigen(inst.id, origen)" :key="m.id">
                <td><input type="checkbox" style="width:auto"
                           :checked="checks[inst.id].mat.has(m.id)"
                           @change="toggleCheck(inst.id, 'mat', m.id)" /></td>
                <td>{{ fmtFecha(m.fecha) }}</td>
                <td>{{ m.descripcion }}</td>
                <td class="right">{{ m.cantidad }} {{ m.unidad }}</td>
                <td>{{ m.trabajador }}</td>
                <td class="right nowrap">
                  <button class="ghost sm" @click="editarMaterial(inst.id, m)">✎</button>
                  <button class="danger sm" @click="borrarLinea('material', inst.id, m.id)">✕</button>
                </td>
              </tr>
              <tr v-if="!materialesPorOrigen(inst.id, origen).length">
                <td colspan="6" class="muted">Sin materiales de {{ origen }}.</td>
              </tr>
            </tbody>
          </table>
        </template>

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

    <!-- Modal de edicion -->
    <div v-if="editing" class="modal-bg" @click.self="editing = null">
      <div class="modal card">
        <h3 style="margin-top:0;">Editar {{ editing.tipo === 'hora' ? 'jornada' : 'material' }}</h3>

        <template v-if="editing.tipo === 'hora'">
          <label><span>Inicio</span><input type="datetime-local" v-model="editing.form.inicio" /></label>
          <label><span>Fin</span><input type="datetime-local" v-model="editing.form.fin" /></label>
          <p class="muted" style="font-size:.82rem;">Las horas se recalculan a partir de inicio y fin.</p>
        </template>

        <template v-else>
          <label><span>Fecha</span><input type="date" v-model="editing.form.fecha" /></label>
          <label><span>Descripcion</span><input v-model="editing.form.descripcion" /></label>
          <div class="row">
            <label style="flex:1"><span>Cantidad</span><input type="number" step="0.01" v-model="editing.form.cantidad" /></label>
            <label style="flex:1"><span>Unidad</span><input v-model="editing.form.unidad" /></label>
            <label style="flex:1"><span>Precio €</span><input type="number" step="0.01" v-model="editing.form.precio_unit" /></label>
          </div>
          <label><span>Origen</span>
            <select v-model="editing.form.origen">
              <option value="empresa">Empresa instaladora</option>
              <option value="cliente">Cliente</option>
            </select>
          </label>
        </template>

        <div style="display:flex; gap:.6rem; justify-content:flex-end; margin-top:.5rem;">
          <button class="ghost" @click="editing = null">Cancelar</button>
          <button class="ok" @click="guardarEdicion">Guardar</button>
        </div>
      </div>
    </div>
  </div>
</template>
