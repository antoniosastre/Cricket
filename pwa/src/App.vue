<script setup>
import { auth, logout } from './api'
import { syncState } from './sync'
import { useRouter } from 'vue-router'
import { limpiarSesion } from './session'

const router = useRouter()
function salir() {
  if (confirm('¿Cerrar sesion? Se perdera cualquier trabajo sin subir.')) {
    limpiarSesion()
    logout()
    router.push('/login')
  }
}
</script>

<template>
  <header v-if="auth.user" class="appbar">
    <span class="brand">⚡ Partes</span>
    <span class="muted">· {{ auth.user.nombre }}</span>
    <span class="spacer"></span>
    <span v-if="!syncState.online" class="tag cliente">sin conexion</span>
    <span v-else-if="syncState.pendientes" class="tag empresa">{{ syncState.pendientes }} por subir</span>
    <button class="sm sec" @click="salir">Salir</button>
  </header>
  <router-view />
</template>
