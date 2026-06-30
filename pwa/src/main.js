import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { initSync } from './sync'
import './styles.css'

initSync()
createApp(App).use(router).mount('#app')
