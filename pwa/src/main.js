import { createApp, watch } from 'vue'
import App from './App.vue'
import router from './router'
import { auth } from './api'
import { initSync } from './sync'
import './styles.css'

watch(() => auth.access, access => { if (!access) router.replace('/login') })
initSync()
createApp(App).use(router).mount('#app')
