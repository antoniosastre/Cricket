<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api, setSession } from '../api'

const router = useRouter()
const trabajadores = ref([])
const usuario = ref('')
const pin = ref('')
const error = ref('')
const loading = ref(false)

onMounted(async () => {
  // Listado publico (solo id+nombre) para el selector de trabajador.
  try { trabajadores.value = await api('/trabajadores?rol=trabajador&login=1') }
  catch { /* sin conexion: se podra escribir el email manualmente */ }
})

async function entrar() {
  error.value = ''
  loading.value = true
  try {
    const data = await api('/auth/login-pin', { method: 'POST', body: { usuario: usuario.value, pin: pin.value } })
    setSession(data)
    router.push('/')
  } catch (e) {
    error.value = e.message || 'No se pudo iniciar sesion'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="screen" style="justify-content:center;">
    <div class="center">
      <h1>⚡ Partes de trabajo</h1>
      <p class="muted">Identifícate para empezar</p>
    </div>
    <div class="panel">
      <label>
        <span>Trabajador</span>
        <select v-if="trabajadores.length" v-model="usuario">
          <option value="" disabled>Selecciona…</option>
          <option v-for="t in trabajadores" :key="t.id" :value="t.id">{{ t.nombre }}</option>
        </select>
        <input v-else v-model="usuario" placeholder="tu-email@empresa.com" />
      </label>
      <label>
        <span>PIN</span>
        <input v-model="pin" type="password" inputmode="numeric" autocomplete="off" placeholder="••••" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button :disabled="loading || !usuario || !pin" @click="entrar">
        {{ loading ? 'Entrando…' : 'Entrar' }}
      </button>
    </div>
  </div>
</template>
