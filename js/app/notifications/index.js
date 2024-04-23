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

const Notifications = ({ initialNotifications, initialCount, centrifuge, namespace, username, theme, onSeeAll, removeURL, removeNotificationsURL }) => {

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

  const onRemove = (notification) => {
    $.ajax(`${removeURL}/${notification.id}?format=json`, {
      type: 'DELETE',
      contentType: 'application/json',
    }).then((res) => {
      setNotifications(Object.values(res.notifications))
      setCount(res.unread)
    })
  }

  const onDeleteAll = async () => {
    return $.ajax(`${removeNotificationsURL}?all=true&format=json`, {
      type: 'POST',
      contentType: 'application/json',
    }).then((res) => {
      setNotifications(Object.values(res.notifications))
      setCount(res.unread)
    })
  }

  const badgeProps = count === 0 ?
    { style: theme === 'dark' ? zeroStyleDark : zeroStyle } : { style: { backgroundColor: '#52c41a' } }

  return (
    <Popover
      placement="bottomRight"
      content={ <NotificationList onSeeAll={ onSeeAll } onRemove={ onRemove } onDeleteAll={ onDeleteAll } count={ count } notifications={ notifications } /> }
      title="Notifications"
      trigger="click">
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
  const centrifuge = new Centrifuge(`${protocol}://${window.location.host}/centrifugo/connection/websocket`)
  centrifuge.setToken(options.token)

  const theme = el.dataset.theme || 'light'

  $.getJSON(options.notificationsURL, { format: 'json' })
  .then(result => {

    const { unread, notifications } = result

    render(<Notifications
      initialNotifications={ notifications }
      initialCount={ unread }
      removeURL={ options.removeNotificationURL }
      removeNotificationsURL={ options.removeNotificationsURL }
      onSeeAll={ () => { window.location.href = options.notificationsURL } }
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
      removeNotificationURL:    window.Routing.generate('profile_notification_remove'),
      removeNotificationsURL: window.Routing.generate('profile_notifications_remove'),
      token:     result.cent_tok,
      namespace: result.cent_ns,
      username:  result.cent_usr,
    }
    bootstrap(document.querySelector('#notifications'), options)
  })
