import { createRouter, createWebHashHistory } from 'vue-router'
import { auth } from './api'
import { session } from './session'
import Login from './views/Login.vue'
import Instalacion from './views/Instalacion.vue'
import Sesion from './views/Sesion.vue'
import Resumen from './views/Resumen.vue'

const routes = [
  { path: '/login', component: Login, meta: { public: true } },
  { path: '/', component: Instalacion },
  { path: '/sesion', component: Sesion },
  { path: '/resumen', component: Resumen },
]

const router = createRouter({ history: createWebHashHistory(), routes })

router.beforeEach((to) => {
  if (!to.meta.public && !auth.access) return '/login'
  if (to.path === '/login' && auth.access) return '/'
  // Si hay sesion activa, llevar directamente a la pantalla de trabajo.
  if (to.path === '/' && session.instalacion_id) return '/sesion'
})

export default router
