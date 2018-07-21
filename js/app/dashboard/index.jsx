import React from 'react'
import { render, findDOMNode } from 'react-dom'
import { Provider } from 'react-redux'
import { DatePicker, LocaleProvider } from 'antd'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import moment from 'moment'
import { I18nextProvider } from 'react-i18next'

import i18n from '../i18n'
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
    <I18nextProvider i18n={i18n}>
      <LeafletMap socket={socket} />
    </I18nextProvider>
  </Provider>,
  document.querySelector('.dashboard__map-container')
)

render(
  <Provider store={store}>
    <I18nextProvider i18n={i18n}>
      <DashboardApp socket={socket} />
    </I18nextProvider>
  </Provider>,
  document.querySelector('.dashboard__aside')
)

render(
  <Provider store={store}>
    <I18nextProvider i18n={i18n}>
      <Filters />
    </I18nextProvider>
  </Provider>,
  document.createElement('div'),
  function () {
    document.querySelector('#dashboard-filters').appendChild(findDOMNode(this))
  }
)

$('#dashboard-filters > a').on('click', function (event) {
  $(this).parent().toggleClass("open")
})

// keep the filters dropdown open if click on filters - close if click outside
$('body').on('click', function (e) {
  if (!$('#dashboard-filters').is(e.target) && $('#dashboard-filters').has(e.target).length === 0) {
    $('#dashboard-filters').removeClass('open')
  }
})

// hide export modal after button click
$('#export-modal button').on('click', () => setTimeout(() => $('#export-modal').modal('hide'), 400))

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
