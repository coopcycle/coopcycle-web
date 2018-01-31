import React from 'react'
import { render, findDOMNode } from 'react-dom'
import { DatePicker, LocaleProvider } from 'antd'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import MapHelper from '../MapHelper'
import Panel from './components/Panel'
import TaskList from './components/TaskList'
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

const proxy = new MapProxy(map)

const unassignedTasks = _.filter(window.AppData.Dashboard.tasks, task => !task.isAssigned)
const assignedTasks = _.filter(window.AppData.Dashboard.tasks, task => task.isAssigned)

_.each(window.AppData.Dashboard.tasks, task => proxy.addTask(task))
_.each(window.AppData.Dashboard.taskLists, (taskList, username) => proxy.addTaskList(username, taskList))

let lastDraggedElement
let waitingComponent
let unassignedTaskList
let userPanelList

const userComponentMap = new Map()

var drake = dragula({
  copy: true,
  copySortSource: false,
  revertOnSpill: true,
  accepts: (el, target, source, sibling) => target !== source
})
.on('cloned', function (clone, original) {
  lastDraggedElement = original
  if ($(original).hasClass('list-group-item')) {
    $(original).addClass('disabled')
  } else {
    $(original).find('.list-group-item').addClass('disabled')
  }
}).on('dragend', function (el) {
  if ($(lastDraggedElement).hasClass('list-group-item')) {
    $(lastDraggedElement).removeClass('disabled')
  } else {
    $(lastDraggedElement).find('.list-group-item').removeClass('disabled')
  }
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

  let tasks = []
  if ($(element).data('task-group') === true) {
    tasks = $(element)
      .children()
      .map((index, el) => $(el).data('task-id'))
      .map((index, taskID) => _.find(window.AppData.Dashboard.tasks, task => task['@id'] === taskID))
      .toArray()
  } else {
    const task = _.find(window.AppData.Dashboard.tasks, task => task['@id'] === $(element).data('task-id'))
    tasks.push(task)
  }

  component.add(tasks)
  unassignedTaskList.remove(tasks)

  $(target).removeClass('dropzone--loading')
  element.remove()
  lastDraggedElement.remove()

});

$('#user-modal button[type="submit"]').on('click', (e) => {
  e.preventDefault()
  const username = $('#user-modal [name="courier"]').val()
  userPanelList.add(username)
  $('#user-modal').modal('hide')
})

render(<Panel heading={() => (
    <h4>
      <span>{ window.AppData.Dashboard.i18n['Unassigned'] }</span>
      <a href="#" className="pull-right" onClick={ e => {
        e.preventDefault();
        $('#task-modal').modal('show')
      }}>
        <i className="fa fa-plus"></i>
      </a>
    </h4>
  )}>
    <TaskList
      ref={ el => unassignedTaskList = el }
      tasks={ unassignedTasks }
      onLoad={ el => drake.containers.push(el) } />
  </Panel>,
  document.querySelector('.dashboard .dashboard__aside__top')
)

render(<Panel heading={() => (
    <h4>
      <span>{ window.AppData.Dashboard.i18n['Assigned'] }</span>
      <a href="#" className="pull-right" onClick={ e => {
        e.preventDefault();
        $('#user-modal').modal('show')
      }}>
        <i className="fa fa-plus"></i> <i className="fa fa-user"></i>
      </a>
    </h4>
  )}>
    <UserPanelList
      ref={ el => userPanelList = el }
      tasks={ assignedTasks }
      taskLists={ window.AppData.Dashboard.taskLists }
      map={ proxy }
      onRemove={task => unassignedTaskList.add(task)}
      onLoad={(component, element) => {
        drake.containers.push(element)
        userComponentMap.set(element, component)
      }}
      onTaskListChange={(username, taskList) => {
        proxy.addTaskList(username, taskList)
      }}
      save={(username, tasks) => {
        const data = tasks.map((task, index) => {
          return {
            task: task['@id'],
            position: index
          }
        })
        return $.ajax({
          url: window.AppData.Dashboard.assignTaskURL.replace('__USERNAME__', username),
          type: 'POST',
          data: JSON.stringify(data),
          contentType: 'application/json',
        })
      }} />
  </Panel>,
  document.querySelector('.dashboard .dashboard__aside__bottom')
)

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

render(
  <LocaleProvider locale={antdLocale}>
    <DatePicker.RangePicker
      style={{ width: '100%' }}
      showTime={{ hideDisabledOptions: true, format: 'HH:mm' }}
      format="YYYY-MM-DD HH:mm"
      defaultValue={[ moment($('#task_doneAfter').val()), moment($('#task_doneBefore').val()) ]}
      onChange={(value, dateString) => {
        const [ doneAfter, doneBefore ] = dateString
        $('#task_doneAfter').val(doneAfter)
        $('#task_doneBefore').val(doneBefore)
      }} />
  </LocaleProvider>,
  document.querySelector('#task_timewindow_rangepicker')
)

const $doneAfterHidden = $('<input>')
  .attr('type', 'hidden')
  .attr('name', $('#task_doneAfter').attr('name'))
  .attr('id', 'task_doneAfter')
  .val($('#task_doneAfter').val())

const $doneBeforeHidden = $('<input>')
  .attr('type', 'hidden')
  .attr('name', $('#task_doneBefore').attr('name'))
  .attr('id', 'task_doneBefore')
  .val($('#task_doneBefore').val())

$doneAfterHidden.appendTo($('#task_timewindow'))
$doneBeforeHidden.appendTo($('#task_timewindow'))

$('#task_doneAfter').closest('.form-group').remove()
$('#task_doneBefore').closest('.form-group').remove()

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
