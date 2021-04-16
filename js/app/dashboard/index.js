import React, { createRef } from 'react'
import { render } from 'react-dom'
import { Provider } from 'react-redux'
import lottie from 'lottie-web'
import { I18nextProvider } from 'react-i18next'
import moment from 'moment'
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

import { taskListUtils, taskAdapter, taskListAdapter } from '../coopcycle-frontend-js/logistics/redux'

function start() {

  const dashboardEl = document.getElementById('dashboard')

  let date = moment(dashboardEl.dataset.date)
  let allTasks = JSON.parse(dashboardEl.dataset.allTasks)
  let taskLists = JSON.parse(dashboardEl.dataset.taskLists)

  // normalize data, keep only task ids, instead of the whole objects
  taskLists = taskLists.map(taskList => taskListUtils.replaceTasksWithIds(taskList))

  const preloadedPositions = JSON.parse(dashboardEl.dataset.positions)
  const positions = preloadedPositions.map(pos => ({
    username: pos.username,
    coords: { lat: pos.latitude, lng: pos.longitude },
    lastSeen: moment(pos.timestamp, 'X'),
  }))

  let preloadedState = {
    logistics : {
      date,
      entities: {
        tasks: taskAdapter.upsertMany(
          taskAdapter.getInitialState(),
          allTasks
        ),
        taskLists: taskListAdapter.upsertMany(
          taskListAdapter.getInitialState(),
          taskLists
        )
      }
    },
    jwt: dashboardEl.dataset.jwt,

    rrules: recurrenceRulesAdapter.upsertMany(
      recurrenceRulesAdapter.getInitialState(),
      JSON.parse(dashboardEl.dataset.rrules)
    ),
    config: {
      centrifugoToken: dashboardEl.dataset.centrifugoToken,
      centrifugoTrackingChannel: dashboardEl.dataset.centrifugoTrackingChannel,
      centrifugoEventsChannel: dashboardEl.dataset.centrifugoEventsChannel,
      stores: JSON.parse(dashboardEl.dataset.stores),
      tags: JSON.parse(dashboardEl.dataset.tags),
      uploaderEndpoint: dashboardEl.dataset.uploaderEndpoint,
      exampleSpreadsheetUrl: dashboardEl.dataset.exampleSpreadsheetUrl,
      couriersList: JSON.parse(dashboardEl.dataset.couriersList),
      nav: dashboardEl.dataset.nav,
      pickupClusterAddresses: JSON.parse(dashboardEl.dataset.pickupClusterAddresses),
    },
    tracking: {
      positions,
    }
  }

  const key = date.format('YYYY-MM-DD')
  const persistedFilters = window.sessionStorage.getItem(`cpccl__dshbd__fltrs__${key}`)
  if (persistedFilters) {
    preloadedState = {
      ...preloadedState,
      settings: {
        filters: JSON.parse(persistedFilters)
      }
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
                <svg xmlns="http://www.w3.org/2000/svg"
                  className="arrow-container"
                  style={{ position: 'absolute', top: '0px', left: '0px', width: '100%', height: '100%', overflow: 'visible', pointerEvents: 'none' }}
                >
                  <defs>
                    <marker id="custom_arrow" markerWidth="4" markerHeight="4" refX="2" refY="2">
                      <circle cx="2" cy="2" r="2" stroke="none" fill="#3498DB"/>
                    </marker>
                  </defs>
                </svg>
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
      // mapRef.current.invalidateSize()
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
