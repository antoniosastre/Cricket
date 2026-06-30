<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api'

const tab = ref('trabajadores')
const tabs = [
  ['trabajadores', 'Trabajadores'],
  ['clientes', 'Clientes'],
  ['instalaciones', 'Instalaciones'],
  ['catalogo', 'Catalogo'],
]

const clientes = ref([])
const instalaciones = ref([])
const trabajadores = ref([])
const catalogo = ref([])
const error = ref('')

const forms = {
  trabajadores: ref({ nombre: '', email: '', rol: 'trabajador', tarifa_hora: '', pin: '', password: '' }),
  clientes: ref({ nombre: '', nif: '', direccion: '', telefono: '', email: '' }),
  instalaciones: ref({ nombre: '', cliente_id: '', direccion: '', descripcion: '' }),
  catalogo: ref({ descripcion: '', unidad: 'ud', precio: '' }),
}

async function loadAll() {
  try {
    ;[clientes.value, instalaciones.value, trabajadores.value, catalogo.value] = await Promise.all([
      api('/clientes'), api('/instalaciones'), api('/trabajadores'), api('/catalogo'),
    ])
  } catch (e) { error.value = e.message }
}

async function crear(res) {
  try {
    await api(`/${res}`, { method: 'POST', body: forms[res].value })
    forms[res].value = Object.fromEntries(Object.keys(forms[res].value).map(k => [k, k === 'rol' ? 'trabajador' : (k === 'unidad' ? 'ud' : '')]))
    await loadAll()
  } catch (e) { alert(e.message) }
}
async function borrar(res, id) {
  if (!confirm('¿Eliminar/dar de baja este elemento?')) return
  try { await api(`/${res}/${id}`, { method: 'DELETE' }); await loadAll() }
  catch (e) { alert(e.message) }
}
async function archivar(inst) {
  const nuevo = inst.estado === 'archivada' ? 'activa' : 'archivada'
  try { await api(`/instalaciones/${inst.id}/estado`, { method: 'POST', body: { estado: nuevo } }); await loadAll() }
  catch (e) { alert(e.message) }
}
onMounted(loadAll)
</script>

<template>
  <div class="container">
    <h2>Administracion</h2>
    <p v-if="error" class="error">{{ error }}</p>
    <div style="display:flex; gap:.5rem; margin-bottom:1rem;">
      <button v-for="[k, l] in tabs" :key="k" :class="tab === k ? '' : 'ghost'" @click="tab = k">{{ l }}</button>
    </div>

    <!-- TRABAJADORES -->
    <div v-show="tab === 'trabajadores'">
      <div class="card">
        <div class="row">
          <label><span>Nombre</span><input v-model="forms.trabajadores.value.nombre" /></label>
          <label><span>Email</span><input v-model="forms.trabajadores.value.email" type="email" /></label>
          <label><span>Rol</span><select v-model="forms.trabajadores.value.rol"><option value="trabajador">Trabajador</option><option value="admin">Admin</option></select></label>
          <label><span>Tarifa €/h</span><input v-model="forms.trabajadores.value.tarifa_hora" type="number" step="0.01" /></label>
          <label><span>PIN (trabajador)</span><input v-model="forms.trabajadores.value.pin" /></label>
          <label><span>Contrasena (admin)</span><input v-model="forms.trabajadores.value.password" type="password" /></label>
          <button @click="crear('trabajadores')">Anadir</button>
        </div>
      </div>
      <table class="card" style="display:table; width:100%;">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th class="right">€/h</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in trabajadores" :key="t.id">
            <td>{{ t.nombre }}</td><td>{{ t.email }}</td><td>{{ t.rol }}</td>
            <td class="right">{{ t.tarifa_hora ?? '—' }}</td>
            <td><span :class="['badge', t.activo ? 'on' : 'off']">{{ t.activo ? 'Activo' : 'Baja' }}</span></td>
            <td class="right"><button class="danger sm" @click="borrar('trabajadores', t.id)">Baja</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- CLIENTES -->
    <div v-show="tab === 'clientes'">
      <div class="card">
        <div class="row">
          <label><span>Nombre</span><input v-model="forms.clientes.value.nombre" /></label>
          <label><span>NIF</span><input v-model="forms.clientes.value.nif" /></label>
          <label><span>Direccion</span><input v-model="forms.clientes.value.direccion" /></label>
          <label><span>Telefono</span><input v-model="forms.clientes.value.telefono" /></label>
          <button @click="crear('clientes')">Anadir</button>
        </div>
      </div>
      <table class="card" style="display:table; width:100%;">
        <thead><tr><th>Nombre</th><th>NIF</th><th>Direccion</th><th>Telefono</th><th></th></tr></thead>
        <tbody>
          <tr v-for="c in clientes" :key="c.id">
            <td>{{ c.nombre }}</td><td>{{ c.nif }}</td><td>{{ c.direccion }}</td><td>{{ c.telefono }}</td>
            <td class="right"><button class="danger sm" @click="borrar('clientes', c.id)">Borrar</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- INSTALACIONES -->
    <div v-show="tab === 'instalaciones'">
      <div class="card">
        <div class="row">
          <label><span>Nombre</span><input v-model="forms.instalaciones.value.nombre" /></label>
          <label><span>Cliente</span>
            <select v-model="forms.instalaciones.value.cliente_id">
              <option value="">— sin cliente —</option>
              <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </label>
          <label><span>Direccion</span><input v-model="forms.instalaciones.value.direccion" /></label>
          <button @click="crear('instalaciones')">Anadir</button>
        </div>
      </div>
      <table class="card" style="display:table; width:100%;">
        <thead><tr><th>Nombre</th><th>Cliente</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="i in instalaciones" :key="i.id">
            <td>{{ i.nombre }}</td><td>{{ i.cliente || '—' }}</td>
            <td><span :class="['badge', i.estado === 'activa' ? 'on' : 'off']">{{ i.estado }}</span></td>
            <td class="right"><button class="ghost sm" @click="archivar(i)">{{ i.estado === 'archivada' ? 'Reactivar' : 'Archivar' }}</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- CATALOGO -->
    <div v-show="tab === 'catalogo'">
      <div class="card">
        <div class="row">
          <label><span>Descripcion</span><input v-model="forms.catalogo.value.descripcion" /></label>
          <label><span>Unidad</span><input v-model="forms.catalogo.value.unidad" /></label>
          <label><span>Precio €</span><input v-model="forms.catalogo.value.precio" type="number" step="0.01" /></label>
          <button @click="crear('catalogo')">Anadir</button>
        </div>
      </div>
      <table class="card" style="display:table; width:100%;">
        <thead><tr><th>Descripcion</th><th>Unidad</th><th class="right">Precio</th><th></th></tr></thead>
        <tbody>
          <tr v-for="m in catalogo" :key="m.id">
            <td>{{ m.descripcion }}</td><td>{{ m.unidad }}</td><td class="right">{{ m.precio ?? '—' }}</td>
            <td class="right"><button class="danger sm" @click="borrar('catalogo', m.id)">Borrar</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
