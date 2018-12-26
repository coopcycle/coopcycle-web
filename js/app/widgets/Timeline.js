import React from 'react'
import { render } from 'react-dom'
import Timeline from 'antd/lib/timeline'
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

  const el = document.createElement('div')

  ul.parentNode.insertBefore(el, ul)
  ul.parentNode.removeChild(ul)

  render(
    <Timeline>
      { events.map(event => (
        <Timeline.Item key={ event.timestamp + '-' + event.name } color={ event.color }>
          <p>{ event.createdAt } { event.name }</p>
          { event.notes && (
            <p>{ event.notes }</p>
          ) }
        </Timeline.Item>
      )) }
    </Timeline>,
    el
  )
}
