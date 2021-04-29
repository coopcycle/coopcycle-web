import React from 'react'
import { render } from 'react-dom'
import cubejs from '@cubejs-client/core'
import { Provider } from 'react-redux'

import LogisticsDashboard from './components/LogisticsDashboard'

import BestRestaurants from './components/BestRestaurants'
import AverageCart from './components/AverageCart'
import OrderCountPerDayOfWeek from './components/OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './components/OrderCountPerHourRange'

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
        <div>
          <div style={{ minHeight: '240px' }}>
            <BestRestaurants cubejsApi={ cubejsApi } />
          </div>
          <div style={{ minHeight: '240px' }}>
            <AverageCart cubejsApi={ cubejsApi } />
          </div>
          <div style={{ minHeight: '240px' }}>
            <OrderCountPerDayOfWeek cubejsApi={ cubejsApi } />
          </div>
          <div style={{ minHeight: '240px' }}>
            <OrderCountPerHourRange cubejsApi={ cubejsApi } />
          </div>
        </div>, rootElement);
      break

    case 'logistics':
    default:
      render(
        <Provider store={ store }>
          <LogisticsDashboard cubejsApi={ cubejsApi } />
        </Provider>, rootElement)
  }
}
