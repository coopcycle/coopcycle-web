import React, { createRef } from 'react'
import { render } from 'react-dom'
import { Provider } from 'react-redux'
import lottie from 'lottie-web'
import { I18nextProvider } from 'react-i18next'
import moment from 'moment'
import _ from 'lodash'
import { ConfigProvider } from 'antd'
import Split from 'react-split'

import i18n, { antdLocale } from '../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import RightPanel from './components/RightPanel'
import LeafletMap from './components/LeafletMap'
import Navbar from './components/Navbar'
import Modals from './components/Modals'
import { updateRightPanelSize } from './redux/actions'
import { recurrenceRulesAdapter } from './redux/selectors'

import 'react-phone-number-input/style.css'
import './dashboard.scss'

function start() {

  const dashboardEl = document.getElementById('dashboard')

  const date = moment(dashboardEl.dataset.date)
  const tasks = JSON.parse(dashboardEl.dataset.tasks)

  const preloadedPositions = JSON.parse(dashboardEl.dataset.positions)
  const positions = preloadedPositions.map(pos => ({
    username: pos.username,
    coords: { lat: pos.latitude, lng: pos.longitude },
    lastSeen: moment(pos.timestamp, 'X'),
  }))

  let preloadedState = {
    dispatch: {
      unassignedTasks: _.filter(tasks, task => !task.isAssigned),
      taskLists: JSON.parse(dashboardEl.dataset.taskLists),
      date,
    },
    tags: JSON.parse(dashboardEl.dataset.tags),
    couriersList: JSON.parse(dashboardEl.dataset.couriersList),
    uploaderEndpoint: dashboardEl.dataset.uploaderEndpoint,
    exampleSpreadsheetUrl: dashboardEl.dataset.exampleSpreadsheetUrl,
    jwt: dashboardEl.dataset.jwt,
    centrifugoToken: dashboardEl.dataset.centrifugoToken,
    centrifugoTrackingChannel: dashboardEl.dataset.centrifugoTrackingChannel,
    centrifugoEventsChannel: dashboardEl.dataset.centrifugoEventsChannel,
    nav: dashboardEl.dataset.nav,
    positions,
    rrules: recurrenceRulesAdapter.upsertMany(
      recurrenceRulesAdapter.getInitialState(),
      JSON.parse(dashboardEl.dataset.rrules)
    ),
    stores: JSON.parse(dashboardEl.dataset.stores),
  }

  const key = date.format('YYYY-MM-DD')
  const persistedFilters = window.sessionStorage.getItem(`cpccl__dshbd__fltrs__${key}`)
  if (persistedFilters) {
    preloadedState = {
      ...preloadedState,
      filters: JSON.parse(persistedFilters)
    }
  }

  const store = createStoreFromPreloadedState(preloadedState)

  const mapRef = createRef()

  render(
    <Provider store={ store }>
      <I18nextProvider i18n={ i18n }>
        <ConfigProvider locale={antdLocale}>
          <Split
            sizes={[ 75, 25 ]}
            style={{ display: 'flex', width: '100%' }}
            onDrag={ sizes => store.dispatch(updateRightPanelSize(sizes[1])) }
            onDragEnd={ () => mapRef.current.invalidateSize() }>
            <div className="dashboard__map">
              <div className="dashboard__toolbar-container">
                <Navbar />
              </div>
              <div className="dashboard__map-container">
                <LeafletMap onLoad={ (e) => {
                  // It seems like a bad way to get a ref to the map,
                  // but we can't use the ref prop
                  mapRef.current = e.target
                }} />
              </div>
            </div>
            <aside className="dashboard__aside">
              <RightPanel />
            </aside>
          </Split>
          <Modals />
        </ConfigProvider>
      </I18nextProvider>
    </Provider>,
    document.getElementById('dashboard'),
    () => {
      anim.stop()
      anim.destroy()
      document.querySelector('.dashboard__loader').remove()

      // Make sure map is rendered correctly with Split.js
      mapRef.current.invalidateSize()
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
