import React from 'react'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import { render } from 'react-dom'
import Modal from 'react-modal'

import i18n from '../../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import Dashboard from './components/Dashboard'

import 'antd/lib/tooltip/style/index.css'

import './index.scss'

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.FoodtechDashboard = (el, preloadedState, options) => {

  Modal.setAppElement(el)

  // TODO Add loader

  $.getJSON(window.Routing.generate('profile_jwt'))
    .then(result => {

      const state = {
        ...preloadedState,
        jwt: result.jwt,
        centrifugo: {
          token:     result.cent_tok,
          namespace: result.cent_ns,
          username:  result.cent_usr,
        }
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
