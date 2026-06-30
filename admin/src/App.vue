<script setup>
import { useRouter } from 'vue-router'
import { auth, logout } from './api'

const router = useRouter()
function doLogout() {
  logout()
  router.push('/login')
}
</script>

<template>
  <header v-if="auth.user" class="topbar">
    <span class="brand">⚡ Partes</span>
    <nav>
      <router-link to="/">Dashboard</router-link>
      <router-link to="/trabajos">Trabajos</router-link>
      <router-link to="/archivado">Archivado</router-link>
      <router-link to="/admin">Administracion</router-link>
    </nav>
    <span class="spacer"></span>
    <span class="muted">{{ auth.user.nombre }}</span>
    <button class="ghost sm" @click="doLogout">Salir</button>
  </header>
  <router-view />
</template>
