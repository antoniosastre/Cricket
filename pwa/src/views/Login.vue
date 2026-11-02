<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, setSession } from '../api'

const router = useRouter()
const usuario = ref('')
const pin = ref('')
const error = ref('')
const loading = ref(false)

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
        <span>Email del trabajador</span>
        <input v-model="usuario" type="email" autocomplete="username" placeholder="tu-email@empresa.com" />
      </label>
      <label>
        <span>PIN</span>
        <input v-model="pin" type="password" inputmode="numeric" autocomplete="off" placeholder="6 cifras o más" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button :disabled="loading || !usuario || !/^[0-9]{6,72}$/.test(pin)" @click="entrar">
        {{ loading ? 'Entrando…' : 'Entrar' }}
      </button>
    </div>
  </div>
</template>
