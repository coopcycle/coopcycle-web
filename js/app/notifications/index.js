import React, { useState, useEffect } from 'react'
import { render } from '../utils/react'
import { Badge, Popover } from 'antd'
import Centrifuge from 'centrifuge'

import NotificationList from './NotificationList'

import './index.scss'

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
    const httpClient = new window._auth.httpClient()
    httpClient.delete(`${removeURL}/${notification.id}?format=json`).then(({ response }) => {
      if (!response) {
        return
      }
      setNotifications(Object.values(response.notifications))
      setCount(response.unread)
    })
  }

  const onDeleteAll = async () => {
    const httpClient = new window._auth.httpClient()
    return httpClient.post(`${removeNotificationsURL}?all=true&format=json`, {}).then(({ response }) => {
      if (!response) {
        return
      }
      setNotifications(Object.values(response.notifications))
      setCount(response.unread)
    })
  }

  const badgeStyle = count === 0 ?
    (theme === 'dark' ? zeroStyleDark : zeroStyle) : { backgroundColor: '#52c41a' }

  return (
    <Popover
      placement="bottomRight"
      content={ <NotificationList onSeeAll={ onSeeAll } onRemove={ onRemove } onDeleteAll={ onDeleteAll } count={ count } notifications={ notifications } /> }
      title="Notifications"
      trigger="click">
      <a href="#" title={ `${count} new notification(s)` }>
        <Badge count={ count } showZero size="small" offset={ [ -2, 2 ] } style={ badgeStyle }>
          <i className="fa fa-bell" style={{ fontSize: '18px', color: 'inherit', verticalAlign: 'middle' }} aria-hidden="true" />
        </Badge>
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

  const theme = el.dataset.notificationTheme || 'light'

  const httpClient = new window._auth.httpClient()
  httpClient.get(options.notificationsURL, { format: 'json' })
  .then(({ response: result }) => {

    if (!result) {
      return
    }

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

// profile_jwt is still needed here, but only for the Centrifugo
// credentials (token/namespace/username) — not for login, which
// window._auth.httpClient already handles (JWT + refresh) by itself.
const httpClient = new window._auth.httpClient()
httpClient.get(window.Routing.generate('profile_jwt'))
  .then(({ response: result }) => {
    if (!result) {
      return
    }
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
