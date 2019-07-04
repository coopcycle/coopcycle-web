import React from 'react'
import { render, findDOMNode } from 'react-dom'
import { Provider } from 'react-redux'
import moment from 'moment'
import lottie from 'lottie-web'
import { I18nextProvider } from 'react-i18next'

import i18n from '../i18n'
import store from './store/store'
import DashboardApp from './app'
import LeafletMap from './components/LeafletMap'
import Navbar from './components/Navbar'
import Filters from './components/Filters'

let mapLoadedResolve, navbarLoadedResolve, dashboardLoadedResolve

const mapLoaded = new Promise((resolve, reject) => mapLoadedResolve = resolve)
const navbarLoaded = new Promise((resolve, reject) => navbarLoadedResolve = resolve)
const dashboardLoaded = new Promise((resolve, reject) => dashboardLoadedResolve = resolve)

function start() {

  Promise
    .all([ mapLoaded, navbarLoaded, dashboardLoaded ])
    .then((values) => {
      anim.stop()
      anim.destroy()
      document.querySelector('.dashboard__loader').remove()
    })

  render(
    <Provider store={store}>
      <I18nextProvider i18n={i18n}>
        <LeafletMap />
      </I18nextProvider>
    </Provider>,
    document.querySelector('.dashboard__map-container'),
    function () {
      mapLoadedResolve()
    }
  )

  render(
    <Provider store={store}>
      <I18nextProvider i18n={i18n}>
        <Navbar />
      </I18nextProvider>
    </Provider>,
    document.querySelector('.dashboard__toolbar-container'),
    function () {
      navbarLoadedResolve()
    }
  )

  render(
    <Provider store={store}>
      <I18nextProvider i18n={i18n}>
        <DashboardApp />
      </I18nextProvider>
    </Provider>,
    document.querySelector('.dashboard__aside'),
    function () {
      dashboardLoadedResolve()
    }
  )

  // hide export modal after button click
  $('#export-modal button').on('click', () => setTimeout(() => $('#export-modal').modal('hide'), 400))
}

const anim = lottie.loadAnimation({
  container: document.querySelector('#dashboard__loader'),
  renderer: 'svg',
  loop: true,
  autoplay: true,
  path: '/img/loading.json'
})

anim.addEventListener('DOMLoaded', function() {
  setTimeout(() => start(), 800)
})
