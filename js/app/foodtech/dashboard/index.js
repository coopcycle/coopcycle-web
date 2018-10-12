import React from 'react'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import { render } from 'react-dom'

import i18n from '../../i18n'
import store from './redux/store'
import { createStoreFromPreloadedState } from './redux/store'
import Dashboard from './components/Dashboard'

const hostname = window.location.hostname,
      socket = io('//' + hostname, { path: '/tracking/socket.io' })

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.FoodtechDashboard = (el, preloadedState) => {

  const store = createStoreFromPreloadedState(preloadedState)

  render(
    <Provider store={ store }>
      <I18nextProvider i18n={ i18n }>
        <Dashboard socket={ socket } />
      </I18nextProvider>
    </Provider>,
    el
  )
}
