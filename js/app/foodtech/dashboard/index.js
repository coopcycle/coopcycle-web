import React from 'react'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import { render } from 'react-dom'
import Modal from 'react-modal'

import i18n from '../../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import Dashboard from './components/Dashboard'

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.FoodtechDashboard = (el, preloadedState, options) => {

  Modal.setAppElement(el)

  // TODO Add loader

  $.getJSON(window.Routing.generate('profile_jwt'))
    .then(token => {

      const state = {
        ...preloadedState,
        jwt: token
      }

      const store = createStoreFromPreloadedState(state)

      render(
        <Provider store={ store }>
          <I18nextProvider i18n={ i18n }>
            <Dashboard onDateChange={ options.onDateChange } />
          </I18nextProvider>
        </Provider>,
        el
      )
    })
}
