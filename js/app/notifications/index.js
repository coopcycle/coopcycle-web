import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import { Badge, Popover } from 'antd'

import NotificationList from './NotificationList'

const zeroStyle = {
  backgroundColor: 'transparent',
  color: 'inherit',
  boxShadow: '0 0 0 1px #d9d9d9 inset'
}

const Notifications = ({ initialNotifications, initialCount, onOpen, socket }) => {

  const [ visible, setVisible ] = useState(false)
  const [ notifications, setNotifications ] = useState(initialNotifications)
  const [ count, setCount ] = useState(initialCount)

  useEffect(() => {
    socket.on(`notifications`, notification => {
      const newNotifications = notifications.slice()
      newNotifications.unshift(notification)
      setNotifications(newNotifications)
    })
    socket.on(`notifications:count`, count => setCount(count))
  }, [])

  useEffect(() => {
    if (visible) {
      onOpen(notifications)
    }
  }, [ visible ])

  const badgeProps = count === 0 ?
    { style: zeroStyle } : { style: { backgroundColor: '#52c41a' } }

  return (
    <Popover
      placement="bottomRight"
      content={ <NotificationList notifications={ notifications } /> }
      title="Notifications"
      trigger="click"
      visible={ visible }
      onVisibleChange={ value => setVisible(value) }
    >
      <a href="#">
        <Badge count={ count } showZero { ...badgeProps } title={ `${count} new notification(s)` } />
      </a>
    </Popover>
  )
}

function bootstrap(el, options) {

  if (!el) {
    return
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

    render(<Notifications
      initialNotifications={ notifications }
      initialCount={ unread }
      onOpen={ (notifications) => {
        const notificationsIds = notifications.map(notification => notification.id)
        $.ajax(options.markAsReadURL, {
          type: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(notificationsIds),
        })
      }}
      socket={ socket } />, el)
  })
  .catch(() => { /* Fail silently */ })
}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(result => {
    const options = {
      notificationsURL: window.Routing.generate('profile_notifications'),
      markAsReadURL: window.Routing.generate('profile_notifications_mark_as_read'),
      jwt: result.jwt,
    }
    bootstrap(document.querySelector('#notifications'), options)
  })
