import React from 'react'
import { render, findDOMNode } from 'react-dom'
import { DatePicker, LocaleProvider } from 'antd'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import MapHelper from '../MapHelper'
import Panel from './components/Panel'
import DeliveryList from './components/DeliveryList'
import UserPanel from './components/UserPanel'
import UserPanelList from './components/UserPanelList'
import MapProxy from './components/MapProxy'
import dragula from 'dragula';
import _ from 'lodash';
import L from 'leaflet'
import moment from 'moment'

const locale = $('html').attr('lang');
const antdLocale = locale === 'fr' ? fr_FR : en_GB

const map = MapHelper.init('map')

const proxy = new MapProxy(map, {
  users: window.AppData.Dashboard.users,
  routingRouteURL: window.AppData.Dashboard.routingRouteURL
})

console.log(window.AppData.Dashboard.deliveries)
console.log(window.AppData.Dashboard.planning)

function isPlanned(delivery) {
  let deliveries = []
  _.each(window.AppData.Dashboard.planning, (items, username) => {
    _.each(items, item => {
      deliveries.push(item.delivery)
    })
  })
  const item = _.find(deliveries, iri => delivery['@id'] === iri)

  return !!item
}

let elementRef
let waitingComponent
let waitingList
let planningList

const userComponentMap = new Map()

var drake = dragula({
  copy: true,
  copySortSource: true,
  revertOnSpill: true,
  accepts: (el, target, source, sibling) => target !== source,
})
.on('cloned', function (clone, original) {
  elementRef = original
  $(original).addClass('disabled')
}).on('dragend', function (el) {
  $(elementRef).removeClass('disabled')
}).on('over', function (el, container, source) {
  if (userComponentMap.has(container)) {
    $(container).addClass('dropzone--over');
  }
}).on('out', function (el, container, source) {
  if (userComponentMap.has(container)) {
    $(container).removeClass('dropzone--over');
  }
}).on('drop', function(element, target, source) {

  const component = userComponentMap.get(target)

  const delivery = _.find(window.AppData.Dashboard.deliveries,
    delivery => delivery['@id'] === element.getAttribute('data-delivery-id'))

  $(target).addClass('dropzone--loading')

  component.add(delivery).then(() => {
    $(target).removeClass('dropzone--loading')
    element.remove()
    waitingList.remove(delivery)
    elementRef.remove()
  })

});

const unscheduled = _.filter(window.AppData.Dashboard.deliveries, delivery => !isPlanned(delivery))

render(<Panel heading={() => (
    <h4>
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
      </LocaleProvider>
    </h4>
  )}>
    <DeliveryList
      ref={ el => waitingList = el }
      deliveries={ unscheduled }
      onLoad={el => {
        drake.containers.push(el)
      }} />
  </Panel>,
  document.querySelector('.dashboard .dashboard__aside__top')
)

$('#user-modal button[type="submit"]').on('click', (e) => {
  e.preventDefault()
  const username = $('#user-modal [name="courier"]').val()
  $.getJSON(window.AppData.Dashboard.userURL.replace('__USERNAME__', username))
    .then(user => {
      planningList.add(user)
      proxy.addUser(user)
      $('#user-modal').modal('hide')
    })
})

render(<Panel heading={() => (
    <h4>
      <span>{ window.AppData.Dashboard.i18n['Dispatched'] }</span>
      <a href="#" className="pull-right" onClick={ e => {
        e.preventDefault();
        $('#user-modal').modal('show')
      }}>
        <i className="fa fa-plus"></i> <i className="fa fa-user"></i>
      </a>
    </h4>
  )}>
    <UserPanelList
      ref={ el => planningList = el }
      users={ window.AppData.Dashboard.users }
      planning={ window.AppData.Dashboard.planning }
      deliveries={ window.AppData.Dashboard.deliveries }
      map={ proxy }
      onRemove={delivery => waitingList.add(delivery)}
      onLoad={(component, element) => {
        drake.containers.push(element)
        userComponentMap.set(element, component)
      }} />
  </Panel>,
  document.querySelector('.dashboard .dashboard__aside__bottom')
)

const hostname = window.location.hostname
const couriersMap = new Map()
const couriersLayer = new L.LayerGroup()

couriersLayer.addTo(map)

const socket = io('//' + hostname, { path: '/tracking/socket.io' })

  socket.on('tracking', data => {
    let marker
    if (!couriersMap.has(data.user)) {
      marker = MapHelper.createMarker(data.coords, 'bicycle', 'circle', '#000')
      const popupContent = `<div class="text-center">${data.user}</div>`
      marker.bindPopup(popupContent, {
        offset: [3, 70]
      })
      couriersLayer.addLayer(marker)
      couriersMap.set(data.user, marker)
    } else {
      marker = couriersMap.get(data.user)
      marker.setLatLng(data.coords).update()
      marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#000'))
    }
  })

  socket.on('online', username => {
    console.log(`User ${username} is connected`)
  })

  socket.on('offline', username => {
    if (!couriersMap.has(username)) {
      console.error(`User ${username} not found`)
      return
    }
    const marker = couriersMap.get(username)
    marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#CCC'))
  })
