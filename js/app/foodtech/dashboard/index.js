import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'

import i18n from '../../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import Dashboard from './components/Dashboard'

import './index.scss'

export function renderDashboard(el, options) {
  const preloadedState = {}

  if ('currentRoute' in el.dataset) {
    preloadedState.currentRoute = el.dataset.currentRoute
  }

  if ('orders' in el.dataset) {
    preloadedState.orders = JSON.parse(el.dataset.orders)['hydra:member']
  }

  if ('date' in el.dataset) {
    preloadedState.date = el.dataset.date
  }

  if ('restaurant' in el.dataset) {
    preloadedState.restaurant = JSON.parse(el.dataset.restaurant)
  }

  if ('showSettings' in el.dataset) {
    preloadedState.showSettings = el.dataset.showSettings === 'true'
  }

  if ('showSearch' in el.dataset) {
    preloadedState.showSearch = el.dataset.showSearch === 'true'
  }

  if ('initialOrder' in el.dataset) {
    preloadedState.initialOrder = JSON.parse(el.dataset.initialOrder)
  }

  if ('preparationDelay' in el.dataset) {
    preloadedState.preparationDelay = parseInt(el.dataset.preparationDelay)
  }

  const collapsedColumns = window.localStorage.getItem("cpccl__fdtch_dshbd__cllpsd_clmns")
  if (collapsedColumns) {
    preloadedState.preferences = {
      ...preloadedState.preferences,
      collapsedColumns: JSON.parse(collapsedColumns)
    }
  }

  Modal.setAppElement(el)

  // TODO Add loader

  $.getJSON(window.Routing.generate('profile_jwt')).then(result => {

    const state = {
      ...preloadedState,
      jwt: result.jwt,
      centrifugo: {
        token: result.cent_tok,
        namespace: result.cent_ns,
        username: result.cent_usr,
      },
    }

    const store = createStoreFromPreloadedState(state)

    const root = createRoot(el)
    root.render(
      <StrictMode>
        <Provider store={ store }>
          <I18nextProvider i18n={ i18n }>
            <Dashboard onDateChange={ options.onDateChange } />
          </I18nextProvider>
        </Provider>
      </StrictMode>)
  })
}
