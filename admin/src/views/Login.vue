<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, setSession } from '../api'

const router = useRouter()
const inicial = ref(false)
const nombre = ref('')
const clave = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

onMounted(async () => {
  try { inicial.value = (await api('/sistema/estado')).necesita_admin }
  catch (e) { error.value = e.message }
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    if (inicial.value) {
      await api('/sistema/admin-inicial', {
        method: 'POST', headers: { 'X-Clave-Despliegue': clave.value },
        body: { nombre: nombre.value, email: email.value, password: password.value },
      })
      clave.value = ''
      inicial.value = false
    }
    const data = await api('/auth/login', { method: 'POST', body: { email: email.value, password: password.value } })
    setSession(data)
    router.push('/')
  } catch (e) {
    error.value = e.message
  } finally { loading.value = false }
}
</script>
<template>
  <main class="container" style="max-width:380px; margin-top:8vh">
    <div class="card">
      <h2>{{ inicial ? 'Primera puesta en marcha' : 'Acceso a administración' }}</h2>
      <form @submit.prevent="submit">
        <template v-if="inicial">
          <label><span>Nombre del administrador</span><input v-model="nombre" required /></label>
          <label><span>Clave de despliegue</span><input v-model="clave" type="password" minlength="32" autocomplete="off" required /></label>
        </template>
        <label><span>Email</span><input v-model="email" type="email" autocomplete="username" required /></label>
        <label><span>Contraseña</span><input v-model="password" type="password" :minlength="inicial ? 10 : undefined" :autocomplete="inicial ? 'new-password' : 'current-password'" required /></label>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
        <button type="submit" :disabled="loading">{{ inicial ? 'Crear administrador' : 'Entrar' }}</button>
      </form>
    </div>
  </main>
</template>
