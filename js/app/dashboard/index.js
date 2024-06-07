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
import { initialState as settingsInitialState, defaultFilters } from './redux/settingsReducers'

import 'react-phone-number-input/style.css'
import './dashboard.scss'

import { taskAdapter, taskListAdapter, tourAdapter } from '../coopcycle-frontend-js/logistics/redux'
import _ from 'lodash'
import axios from 'axios'

async function start() {

  const dashboardEl = document.getElementById('dashboard')
  const jwtToken = dashboardEl.dataset.jwt
  const baseUrl = location.protocol + '//' + location.host
  const headers = {
    'Authorization': `Bearer ${jwtToken}`,
    'Accept': 'application/ld+json',
    'Content-Type': 'application/ld+json'
  }

  let date = moment(dashboardEl.dataset.date)

  const tasksRequest = axios.create({ baseURL: baseUrl }).get(`${ window.Routing.generate('api_tasks_get_collection') }?date=${date.format('YYYY-MM-DD')}`, { headers: headers})
  const tasksListsRequest = axios.create({ baseURL: baseUrl }).get(`${ window.Routing.generate('api_task_lists_v2_collection') }?date=${date.format('YYYY-MM-DD')}`, {headers: headers})
  const toursRequest = axios.create({ baseURL: baseUrl }).get(`${ window.Routing.generate('api_tours_get_collection') }?date=${date.format('YYYY-MM-DD')}`, {headers: headers})

  let allTasks
  let taskLists
  let tours

  await Promise.all([tasksRequest, tasksListsRequest, toursRequest]).then((values) => {
    const [taskRes, taskListRes, toursRes] = values
    allTasks = taskRes.data['hydra:member']
    taskLists = taskListRes.data['hydra:member']
    tours = toursRes.data['hydra:member']
  })

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
        ),
        tours: tourAdapter.upsertMany(
          tourAdapter.getInitialState(),
          tours
        )
      }
    },
    jwt: jwtToken,
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
      exportEnabled: dashboardEl.dataset.exportEnabled,
    },
    tracking: {
      positions,
    },
    settings: settingsInitialState,
  }

  const persistedFilters = JSON.parse(window.localStorage.getItem("cpccl__dshbd__fltrs"))
  const initialFilters = {...defaultFilters, ...persistedFilters}
  if (persistedFilters) {
    preloadedState = {
      ...preloadedState,
      settings: {
        filters: initialFilters
      }
    }
  }

  const persistentRules = window.sessionStorage.getItem(`recurrence_rules_visible`)
  if (persistentRules) {
    preloadedState = {
      ...preloadedState,
      settings: {
        ...preloadedState.settings,
        isRecurrenceRulesVisible: JSON.parse(persistentRules)
      }
    }
  }

  const persistedUseAvatarColors = window.sessionStorage.getItem(`use_avatar_colors`) || true
  if (persistedUseAvatarColors) {
    preloadedState = {
      ...preloadedState,
      settings: {
        ...preloadedState.settings,
        useAvatarColors: JSON.parse(persistedUseAvatarColors)
      }
    }
  }

  const persistedToursEnabled = window.localStorage.getItem(`cpccl__dshbd__tours_enabled`)
  if (persistedToursEnabled) {
    preloadedState = {
      ...preloadedState,
      settings: {
        ...preloadedState.settings,
        toursEnabled: JSON.parse(persistedToursEnabled)
      }
    }
  }

  // the empty tour panels are initially open
  let expandedToursIds = []
  tours.forEach((tour) => {if (tour.items.length == 0) {expandedToursIds.push(tour['@id'])}})

  _.merge(preloadedState, {
    logistics: {
      ui: {
        expandedTourPanelsIds: expandedToursIds,
        loadingTourPanelsIds: [],
        unassignedTasksIdsOrder: [],
        unassignedToursOrGroupsOrderIds: []
      }
    }
  })

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
  start()
})
