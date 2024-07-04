import React from 'react'
import { createRoot } from 'react-dom/client'
import cubejs from '@cubejs-client/core'
import { Provider } from 'react-redux'

import Dashboard from './components/Dashboard'

import './index.scss'

import createStore from './redux/store'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const { dateRange, view, zeroWaste, uiTasksMetricsEnabled } = { ...rootElement.dataset }

  const store = createStore({
    dateRange,
    view,
    zeroWaste: JSON.parse(zeroWaste),
    uiTasksMetricsEnabled,
  })

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  createRoot(rootElement).render(
    <Provider store={ store }>
      <Dashboard cubejsApi={ cubejsApi } />
    </Provider>
  )
}
