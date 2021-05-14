import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import { Badge, Popover } from 'antd'
import Centrifuge from 'centrifuge'

import NotificationList from './NotificationList'

const zeroStyle = {
  backgroundColor: 'transparent',
  color: 'inherit',
  boxShadow: '0 0 0 1px #d9d9d9 inset'
}

const zeroStyleDark = {
  backgroundColor: '#9d9d9d',
  color: 'white',
  boxShadow: '0 0 0 1px #d9d9d9 inset'
}

const Notifications = ({ initialNotifications, initialCount, onOpen, centrifuge, namespace, username, theme }) => {

  const [ visible, setVisible ] = useState(false)
  const [ notifications, setNotifications ] = useState(initialNotifications)
  const [ count, setCount ] = useState(initialCount)

  useEffect(() => {
    centrifuge.subscribe(`${namespace}_events#${username}`, message => {
      const { event } = message.data

      switch (event.name) {
        case 'notifications':
          setNotifications(prevNotifications => {
            const newNotifications = [ event.data ]

            return [ ...newNotifications, ...prevNotifications ]
          })
          break
        case 'notifications:count':
          setCount(event.data)
          break
      }
    })
    centrifuge.connect()
  }, [])

  useEffect(() => {
    if (visible) {
      onOpen(notifications)
    }
  }, [ visible ])

  const badgeProps = count === 0 ?
    { style: theme === 'dark' ? zeroStyleDark : zeroStyle } : { style: { backgroundColor: '#52c41a' } }

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

  const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'
  const centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`)
  centrifuge.setToken(options.token)

  const theme = el.dataset.theme || 'light'

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
      centrifuge={ centrifuge }
      namespace={ options.namespace }
      username={ options.username }
      theme={ theme } />, el)
  })
  .catch(() => { /* Fail silently */ })
}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(result => {
    const options = {
      notificationsURL: window.Routing.generate('profile_notifications'),
      markAsReadURL:    window.Routing.generate('profile_notifications_mark_as_read'),
      token:     result.cent_tok,
      namespace: result.cent_ns,
      username:  result.cent_usr,
    }
    bootstrap(document.querySelector('#notifications'), options)
  })
