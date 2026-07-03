import React from 'react'
import { createRoot } from 'react-dom/client'
import cubejs from '@cubejs-client/core'
import { CubeProvider } from '@cubejs-client/react'
import { Provider } from 'react-redux'

import Dashboard from './components/Dashboard'

import './index.scss'

import createStore from './redux/store'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const { dateRange, view, zeroWaste, tags, uiTasksMetricsEnabled } = { ...rootElement.dataset }

  const preloadedState = {
    view,
    zeroWaste: JSON.parse(zeroWaste),
    tags: JSON.parse(tags),
    uiTasksMetricsEnabled,
  }

  if (dateRange && dateRange.includes(',')) {
    preloadedState.dateRange = dateRange.split(',')
  }

  const store = createStore(preloadedState)

  const cubeApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  createRoot(rootElement).render(
    <Provider store={ store }>
      <CubeProvider cubeApi={ cubeApi }>
        <Dashboard />
      </CubeProvider>
    </Provider>
  )
}
