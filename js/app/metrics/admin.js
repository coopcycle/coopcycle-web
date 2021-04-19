import React from 'react'
import ReactDOM from 'react-dom'
import cubejs from '@cubejs-client/core';

import AverageDistance from './components/AverageDistance'
import NumberOfTasks from './components/NumberOfTasks'
import BestRestaurants from './components/BestRestaurants'

import './index.scss'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  switch (rootElement.dataset.view) {
    case 'marketplace':
      ReactDOM.render(
        <div>
          <div style={{ minHeight: '240px' }}>
            <BestRestaurants cubejsApi={ cubejsApi } />
          </div>
        </div>, rootElement);
      break

    case 'logistics':
    default:
      ReactDOM.render(
        <div>
          <div style={{ minHeight: '240px' }}>
            <AverageDistance cubejsApi={ cubejsApi } />
          </div>
          <div style={{ minHeight: '240px' }}>
            <NumberOfTasks cubejsApi={ cubejsApi } />
          </div>
        </div>, rootElement);
  }
}
