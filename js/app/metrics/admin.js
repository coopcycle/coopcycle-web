import React from 'react'
import { render } from 'react-dom'
import cubejs from '@cubejs-client/core'
import { Provider } from 'react-redux'

import Dashboard from './components/Dashboard'

import './index.scss'

import store from './redux/store'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  render(
    <Provider store={ store }>
      <Dashboard cubejsApi={ cubejsApi } />
    </Provider>,
    rootElement
  )
}
