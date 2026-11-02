<script setup>
import { useRouter } from 'vue-router'
import { auth, logout } from './api'
import { pendingCount } from './db'
import { syncState } from './sync'
import { session } from './session'

const router = useRouter()
async function salir() {
  if (session.instalacion_id) {
    alert('Confirma o descarta la instalación en curso antes de salir.')
    return
  }
  const pendientes = await pendingCount(auth.user.id)
  const aviso = pendientes ? 'Los registros pendientes se quedarán en este móvil y se subirán cuando vuelvas a entrar. ¿Salir?' : '¿Cerrar sesión?'
  if (confirm(aviso)) {
    logout()
    router.replace('/login')
  }
}
</script>
<template>
  <header v-if="auth.user" class="appbar">
    <span class="brand">⚡ Partes</span>
    <span class="muted">· {{ auth.user.nombre }}</span>
    <span class="spacer"></span>
    <span v-if="syncState.error" role="status" class="tag cliente">Falló la subida · {{ syncState.error }}</span>
    <span v-if="!syncState.online" class="tag cliente">Sin conexión</span>
    <span v-if="syncState.pendientes" class="tag empresa">{{ syncState.pendientes }} por subir</span>
    <button class="sm sec" @click="salir">Salir</button>
  </header>
  <router-view />
</template>
