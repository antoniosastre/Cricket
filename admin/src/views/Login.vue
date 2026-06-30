<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, setSession } from '../api'

const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const data = await api('/auth/login', { method: 'POST', body: { email: email.value, password: password.value } })
    setSession(data)
    router.push('/')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container" style="max-width: 380px; margin-top: 8vh;">
    <div class="card">
      <h2 style="margin-top:0;">Acceso administracion</h2>
      <form @submit.prevent="submit">
        <label><span>Email</span><input v-model="email" type="email" autocomplete="username" required /></label>
        <label><span>Contrasena</span><input v-model="password" type="password" autocomplete="current-password" required /></label>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" :disabled="loading" style="width:100%;">
          {{ loading ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>
    </div>
  </div>
</template>
