<script setup>
import { ref, onMounted } from 'vue'
import { api, download } from '../api'

const lotes = ref([])
const error = ref('')

async function load() {
  try { lotes.value = await api('/archivado') }
  catch (e) { error.value = e.message }
}
async function exportar(id) {
  await download(`/facturacion/${id}/export.csv`, `facturacion_lote_${id}.csv`)
}
async function revertir(id) {
  if (!confirm('¿Revertir este lote? Las lineas volveran a pendientes de facturar.')) return
  try { await api(`/facturacion/${id}/revertir`, { method: 'POST' }); await load() }
  catch (e) { alert(e.message) }
}
function fmt(s) { return s ? s.substring(0, 16).replace('T', ' ') : '' }
onMounted(load)
</script>

<template>
  <div class="container">
    <h2>Archivado · Lotes facturados</h2>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="!lotes.length" class="empty">Todavia no hay nada facturado.</div>
    <table class="card" v-else style="display:table; width:100%;">
      <thead><tr><th>Fecha</th><th>Referencia</th><th>Admin</th><th class="right">Horas</th><th class="right">Materiales</th><th></th></tr></thead>
      <tbody>
        <tr v-for="l in lotes" :key="l.id">
          <td>{{ fmt(l.fecha) }}</td>
          <td>{{ l.referencia || '—' }}</td>
          <td>{{ l.admin }}</td>
          <td class="right">{{ l.total_horas }}</td>
          <td class="right">{{ l.total_mat }}</td>
          <td class="right">
            <button class="ghost sm" @click="exportar(l.id)">CSV</button>
            <button class="danger sm" @click="revertir(l.id)">Revertir</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
