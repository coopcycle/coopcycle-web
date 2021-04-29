import React from 'react'
import { render } from 'react-dom'
import cubejs from '@cubejs-client/core'
import { Provider } from 'react-redux'

import LogisticsDashboard from './components/LogisticsDashboard'
import MarketplaceDashboard from './components/MarketplaceDashboard'

import './index.scss'

import store from './redux/store'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  switch (rootElement.dataset.view) {
    case 'marketplace':
      render(
        <Provider store={ store }>
          <MarketplaceDashboard cubejsApi={ cubejsApi } />
        </Provider>, rootElement);
      break

    case 'logistics':
    default:
      render(
        <Provider store={ store }>
          <LogisticsDashboard cubejsApi={ cubejsApi } />
        </Provider>, rootElement)
  }
}
