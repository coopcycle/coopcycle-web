import React, { createRef } from 'react'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import lottie from 'lottie-web'
import { I18nextProvider } from 'react-i18next'
import moment from 'moment'
import Split from 'react-split'

import i18n from '../i18n'

import { createStoreFromPreloadedState } from './redux/store'
import RightPanel from './components/RightPanel'
import LeafletMap from './components/LeafletMap'
import Navbar from './components/Navbar'
import Modals from './components/Modals'
import { updateRightPanelSize } from './redux/actions'
import { recurrenceRulesAdapter } from './redux/selectors'
import { initialState as settingsInitialState, defaultFilters, defaultSettings, defaultMapFilters } from './redux/settingsReducers'

import 'react-phone-number-input/style.css'
import './dashboard.scss'

import { organizationAdapter, taskAdapter, taskListAdapter, tourAdapter, trailerAdapter, vehicleAdapter, warehouseAdapter } from '../coopcycle-frontend-js/logistics/redux'
import _ from 'lodash'
import { createClient } from './utils/client'
import { RootWithDefaults } from '../utils/react'

const dashboardEl = document.getElementById('dashboard')
const date = moment(dashboardEl.dataset.date)
const jwtToken = dashboardEl.dataset.jwt


async function start(tasksRequest, tasksListsRequest, toursRequest) {

  let allTasks
  let taskLists
  let tours

  await Promise.all([tasksRequest, tasksListsRequest, toursRequest]).then((values) => {
    const [taskRes, taskListRes, toursRes] = values
    allTasks = taskRes // paginatedRequest returns data directly
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
        ),
        organizations: organizationAdapter.getInitialState(),
        vehicles: vehicleAdapter.getInitialState(),
        trailers: trailerAdapter.getInitialState(),
        warehouses: warehouseAdapter.getInitialState(),
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
  const persistedMapFilters = JSON.parse(window.localStorage.getItem("cpccl__dshbd__map__fltrs"))
  const persistedSettings = JSON.parse(window.localStorage.getItem("cpccl__dshbd__settings"))

  const initialFilters = {...defaultFilters, ...persistedFilters}
  const initialMapFilters = {...defaultMapFilters, ...persistedMapFilters}
  const initialSettings = {...defaultSettings, ...persistedSettings}

  preloadedState = {
    ...preloadedState,
    settings: {
      ...initialSettings,
      filters: initialFilters,
      mapFilters: initialMapFilters,
      isDefaultFilters: persistedFilters ? _.isEqual(persistedFilters, defaultFilters) : true
    }
  }

  // the empty tour panels are initially open
  let expandedToursIds = []
  tours.forEach((tour) => {if (tour.items.length == 0) {expandedToursIds.push(tour['@id'])}})

  _.merge(preloadedState, {
    logistics: {
      ui: {
        expandedTourPanelsIds: expandedToursIds,
        expandedTaskListPanelsIds: [],
        expandedTasksGroupPanelIds: [],
        loadingTourPanelsIds: [],
        unassignedTasksIdsOrder: [],
        unassignedToursOrGroupsOrderIds: [],
        organizationsLoading: true,
        trailersLoading: true,
        vehiclesLoading: true,
        warehousesLoading: true,
      }
    }
  })

  const store = createStoreFromPreloadedState(preloadedState)

  const mapRef = createRef()

  const root = createRoot(document.getElementById('dashboard'))

  root.render(
    <RootWithDefaults>
      <Provider store={ store }>
        <I18nextProvider i18n={ i18n }>
            <div className="dashboard__toolbar-container">
              <Navbar />
            </div>
            <div className="dashboard__content">
              <Split
                sizes={[ 75, 25 ]}
                style={{ display: 'flex', width: '100%', height: '100%' }}
                onDrag={ sizes => store.dispatch(updateRightPanelSize(sizes[1])) }
                onDragEnd={ () => mapRef.current.invalidateSize() }>
                <div className="dashboard__map">
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
                  <RightPanel loadingAnim={loadingAnim} />
                </aside>
              </Split>
            </div>
            <Modals />
        </I18nextProvider>
      </Provider>
    </RootWithDefaults>
  )

  // hide export modal after button click
  $('#export-modal button').on('click', () => setTimeout(() => $('#export-modal').modal('hide'), 400))
}

const loadingAnim = lottie.loadAnimation({
  container: document.querySelector('#dashboard__loader'),
  renderer: 'svg',
  loop: true,
  autoplay: true,
  path: '/img/loading.json'
})

loadingAnim.addEventListener('DOMLoaded', function() {
  const headers = {
    'Authorization': `Bearer ${jwtToken}`,
    'Accept': 'application/ld+json',
    'Content-Type': 'application/ld+json'
  }

  const client = createClient(() => {}) // do-nothing dispatch function, as we have a fresh token from the initial load + no initialized store yet

  const tasksRequest = client.paginatedRequest({
    method: 'GET',
    url: `${ window.Routing.generate('_api_/tasks.{_format}_get_collection') }?date=${date.format('YYYY-MM-DD')}&pagination=true&itemsPerPage=100`,
    headers: headers
  })

  const tasksListsRequest = client.request({
    method: 'GET',
    url: `${ window.Routing.generate('_api_/task_lists/v2_get_collection') }?date=${date.format('YYYY-MM-DD')}`,
    headers: headers
  })

  const toursRequest = client.request({
    method: 'GET',
    url: `${ window.Routing.generate('_api_/tours.{_format}_get_collection') }?date=${date.format('YYYY-MM-DD')}`,
    headers: headers
  })

  // the delay is here to avoid a glitch in the animation when there is no tasks to load
  // fire the initial loading requests then wait
  setTimeout(() => start(
    tasksRequest,
    tasksListsRequest,
    toursRequest
  ), 400)
})
