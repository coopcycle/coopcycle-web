import React from 'react'
import { createRoot } from 'react-dom/client'
import { Timeline } from 'antd'
import moment from 'moment'

const defaultOptions = {
  itemColor: () => 'blue',
  format: 'LT'
}

export default function(ul, options) {

  options = options || defaultOptions

  const items = [].slice.call(ul.querySelectorAll('li'))

  const itemColor = options.itemColor || defaultOptions.itemColor
  const format = options.format || defaultOptions.format

  const events = items.map(item => {
    const time = item.querySelector('time')
    const notes = item.querySelector('pre')

    return {
      createdAt: moment(time.getAttribute('datetime')).format(format),
      timestamp: moment(time.getAttribute('datetime')).unix(),
      name: item.getAttribute('data-event'),
      notes: notes ? notes.textContent : null,
      color: itemColor(item)
    }
  })

  // Convert events to new Timeline items format
  const timelineItems = events.map(event => ({
    key: event.timestamp + '-' + event.name,
    color: event.color,
    children: (
      <>
        <p>{event.createdAt} {event.name}</p>
        {event.notes && <p>{event.notes}</p>}
      </>
    )
  }))

  const el = document.createElement('div')

  ul.parentNode.insertBefore(el, ul)
  ul.parentNode.removeChild(el)

  createRoot(el).render(
    <Timeline items={timelineItems} />
  )
}
