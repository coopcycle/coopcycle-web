import React from 'react'
import { render, findDOMNode } from 'react-dom'
import { Provider } from 'react-redux'
import { DatePicker, LocaleProvider } from 'antd'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import moment from 'moment'
import store from './store/store'
import DashboardApp from './app'
import LeafletMap from './components/LeafletMap'
import Filters from './components/Filters'

const locale = $('html').attr('lang'),
      antdLocale = locale === 'fr' ? fr_FR : en_GB,
      hostname = window.location.hostname,
      socket = io('//' + hostname, { path: '/tracking/socket.io' })

render(
  <Provider store={store}>
    <LeafletMap socket={socket} />
  </Provider>,
  document.querySelector('.dashboard__map-container')
)

render(
  <Provider store={store}>
    <DashboardApp socket={socket} />
  </Provider>,
  document.querySelector('.dashboard__aside')
)

let li = document.createElement('li');

render(
  <Provider store={store}>
    <Filters />
  </Provider>,
  li,
  function () {
    document.querySelector('#dashboard-controls').appendChild(findDOMNode(this))
})

render(
  <LocaleProvider locale={antdLocale}>
    <DatePicker
      format={ 'll' }
      defaultValue={ moment(window.AppData.Dashboard.date) }
      onChange={(date, dateString) => {
        if (date) {
          const dashboardURL = window.AppData.Dashboard.dashboardURL.replace('__DATE__', date.format('YYYY-MM-DD'))
          window.location.replace(dashboardURL)
        }
      }} />
  </LocaleProvider>,
  document.querySelector('#date-picker')
)
