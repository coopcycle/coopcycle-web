import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import NotificationList from './NotificationList'
import i18n from '../i18n'

function bootstrap($popover, options) {

  if ($popover.length === 0) {
    return
  }

  const el = document.createElement('div')
  const notificationsListRef = React.createRef()

  const initPopover = () => {

    $popover.popover({
      placement: 'bottom',
      container: 'body',
      html: true,
      content: el,
      template: `<div class="popover" role="tooltip">
        <div class="arrow"></div>
        <div class="popover-content nopadding"></div>
      </div>`,
    })

    $popover.on('shown.bs.popover', () => {
      const notifications = notificationsListRef.current
        .toArray()
        .map(notification => notification.id)
      $.ajax(options.markAsReadURL, {
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(notifications),
      })
    })
  }

  const hostname = `//${window.location.hostname}`
                 + (window.location.port ? `:${window.location.port}` : '')

  const socket = io(hostname, {
    path: '/tracking/socket.io',
    query: {
      token: options.jwt,
    },
    transports: [ 'websocket' ],
  })

  $.getJSON(options.notificationsURL, { format: 'json' })
  .then(result => {

    const { unread, notifications } = result

    options.elements.count.innerHTML = unread

    render(
      <I18nextProvider i18n={ i18n }>
        <NotificationList
          ref={ notificationsListRef }
          notifications={ notifications }
          url={ options.notificationsURL } />
      </I18nextProvider>,
      el,
      () => {

        initPopover()

        socket.on(`notifications`, notification => notificationsListRef.current.unshift(notification))
        socket.on(`notifications:count`, count => options.elements.count.innerHTML = count)
      }
    )
  })
  .catch(() => { /* Fail silently */ })
}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(jwt => {
    const options = {
      notificationsURL: window.Routing.generate('profile_notifications'),
      markAsReadURL: window.Routing.generate('profile_notifications_mark_as_read'),
      jwt: jwt,
      elements: {
        count: document.querySelector('#notifications .badge')
      },
    }
    bootstrap($('#notifications'), options)
  })
