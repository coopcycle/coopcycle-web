import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'

import './css/frontend.scss';

// https://alpinejs.dev/essentials/installation
window.Alpine = Alpine

Alpine.plugin(collapse)
Alpine.start()
