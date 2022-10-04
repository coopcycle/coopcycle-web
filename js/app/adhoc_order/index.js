import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import { Provider } from 'react-redux'

import i18n from '../i18n'
import AdhocOrderForm from './AdhocOrderForm'
import { createStoreFromPreloadedState } from './redux/store'

const container = document.getElementById('adhoc-order')

if (container) {
  Modal.setAppElement(container)

  let preloadedState = {
    jwt: container.dataset.jwt,
    restaurant: JSON.parse(container.dataset.restaurant),
    taxCategories: JSON.parse(container.dataset.taxCategories),
  }

  const store = createStoreFromPreloadedState(preloadedState)

  render(
    <Provider store={store}>
      <I18nextProvider i18n={i18n}>
        <AdhocOrderForm />
      </I18nextProvider>
    </Provider>,
    container
  )
}
