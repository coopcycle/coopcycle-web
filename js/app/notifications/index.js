import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import NotificationList from './NotificationList'
import i18n from '../i18n'

function bootstrap($popover, options) {

  if ($popover.length === 0) {
    return
  }

  let template = document.createElement('script')
  template.type = 'text/template'
  document.body.appendChild(template)

  const notificationsListRef = React.createRef()

  const initPopover = () => {

    $popover.popover({
      placement: 'bottom',
      container: 'body',
      html: true,
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

  const setPopoverContent = () => {
    $popover.attr('data-content', template.innerHTML)
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

  Promise.all([
    $.getJSON(options.unreadCountURL),
    $.getJSON(options.notificationsURL, { format: 'json' })
  ]).then(values => {

    const [ count, notifications ] = values

    options.elements.count.innerHTML = count

    render(
      <I18nextProvider i18n={ i18n }>
        <NotificationList
          ref={ notificationsListRef }
          notifications={ notifications }
          url={ options.notificationsURL }
          emptyMessage={ options.emptyMessage }
          onUpdate={ () => setPopoverContent() } />
      </I18nextProvider>,
      template,
      () => {
        setPopoverContent()
        initPopover()

        socket.on(`notifications`, notification => notificationsListRef.current.unshift(notification))
        socket.on(`notifications:count`, count => options.elements.count.innerHTML = count)
      }
    )
  })
}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(jwt => {
    const options = {
      notificationsURL: window.Routing.generate('profile_notifications'),
      unreadCountURL: window.Routing.generate('profile_notifications_unread'),
      markAsReadURL: window.Routing.generate('profile_notifications_mark_as_read'),
      jwt: jwt,
      elements: {
        count: document.querySelector('#notifications .badge')
      },
    }
    bootstrap($('#notifications'), options)
  })
