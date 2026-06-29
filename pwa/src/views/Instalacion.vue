<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { cachePut, cacheGet } from '../db'
import { startSession } from '../session'

const router = useRouter()
const instalaciones = ref([])
const seleccion = ref('')
const nuevaNombre = ref('')
const nuevaDir = ref('')
const mostrarNueva = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    instalaciones.value = await api('/instalaciones?estado=activa')
    await cachePut('instalaciones', instalaciones.value) // cache para offline
  } catch {
    instalaciones.value = (await cacheGet('instalaciones')) || []
  }
})

function comenzar() {
  const inst = instalaciones.value.find(i => i.id === Number(seleccion.value))
  if (!inst) return
  startSession(inst)
  router.push('/sesion')
}

async function crear() {
  error.value = ''
  if (!navigator.onLine) { error.value = 'Para crear una instalacion nueva necesitas conexion.'; return }
  if (!nuevaNombre.value.trim()) { error.value = 'Indica un nombre.'; return }
  try {
    const res = await api('/instalaciones', { method: 'POST', body: { nombre: nuevaNombre.value, direccion: nuevaDir.value } })
    startSession({ id: res.id, nombre: res.nombre })
    router.push('/sesion')
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <div class="screen">
    <h2>¿Dónde vas a trabajar?</h2>

    <div class="panel" v-if="!mostrarNueva">
      <label>
        <span>Instalación</span>
        <select v-model="seleccion">
          <option value="" disabled>Selecciona una instalación…</option>
          <option v-for="i in instalaciones" :key="i.id" :value="i.id">
            {{ i.nombre }}<template v-if="i.cliente"> · {{ i.cliente }}</template>
          </option>
        </select>
      </label>
      <button :disabled="!seleccion" @click="comenzar">Empezar</button>
      <button class="sec" style="margin-top:.6rem;" @click="mostrarNueva = true">+ Nueva instalación</button>
    </div>

    <div class="panel" v-else>
      <label><span>Nombre</span><input v-model="nuevaNombre" placeholder="Ej. Portal 3 - Cuadro" /></label>
      <label><span>Dirección (opcional)</span><input v-model="nuevaDir" /></label>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="ok" @click="crear">Crear y empezar</button>
      <button class="sec" style="margin-top:.6rem;" @click="mostrarNueva = false">Volver</button>
    </div>

    <p v-if="error && !mostrarNueva" class="error">{{ error }}</p>
  </div>
</template>
