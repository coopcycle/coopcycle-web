import React from 'react'
import { Timeline } from 'antd'
import moment from 'moment'

export default function TaskTimeline({ isLoadingEvents, events }) {
  if (isLoadingEvents) {
    return (
      <div className="text-center">
        <i className="fa fa-spinner fa-spin"></i>
      </div>
    )
  }

  const itemColor = event => {
    switch (event.name) {
      case 'task:done':
        return 'green'
      case 'task:failed':
      case 'task:cancelled':
        return 'red'
      case 'task:rescheduled':
      case 'task:incident-reported':
        return 'orange'
      default:
        return 'blue'
    }
  }

  events.sort((a, b) => {
    return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1
  })

  const timelineItems = events.map(event => ({
    key: event.createdAt + '-' + event.name,
    color: itemColor(event),
    children: (
      <>
        <p>
          {moment(event.createdAt).format('lll')} {event.name}
        </p>
        {event.data.reason && (
          <p style={{ fontFamily: 'monospace' }}>{event.data.reason}</p>
        )}
        {event.data.notes && (
          <p>
            <i className="fa fa-comment" aria-hidden="true"></i>{' '}
            {event.data.notes}
          </p>
        )}
      </>
    ),
  }))

  return <Timeline items={timelineItems} />
}
