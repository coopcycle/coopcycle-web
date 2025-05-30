import React from 'react'
import { Timeline } from 'antd'
import { useTranslation } from 'react-i18next'
import { taskTypeColor, taskTypeListIcon } from '../../styles'
import { asText } from '../ShippingTimeRange'

const Dot = ({ type }) => {
  return (
    <i
      className={`fa ${taskTypeListIcon(type)}`}
      style={{ color: taskTypeColor(type) }}
    />
  )
}

export default ({
  tasks,
  withTimeRange = false,
  withDescription = false,
  withPackages = false,
}) => {
  const { t } = useTranslation()

  return (
    <Timeline data-testid="delivery-itinerary">
      {tasks.map((task, index) => (
        <Timeline.Item dot={<Dot type={task.type} />} key={`task-${index}`}>
          <div>
            {task.type === 'PICKUP'
              ? t('DELIVERY_PICKUP')
              : t('DELIVERY_DROPOFF')}
            {task.address?.name ? (
              <span>
                : <span className="font-weight-bold">{task.address?.name}</span>
              </span>
            ) : null}
            {withTimeRange ? (
              <span className="pull-right">
                <i className="fa fa-clock-o" />
                {' ' + asText([task.after, task.before])}
              </span>
            ) : null}
          </div>
          {task.address?.streetAddress ? (
            <div>{task.address?.streetAddress}</div>
          ) : null}
          {withDescription && task.address.description ? (
            <div className="speech-bubble">
              <i className="fa fa-quote-left" />{' '}
              {' ' + task.address.description}
            </div>
          ) : null}
          {withPackages ? (
            <ul>
              {task.packages?.map((p, index) =>
                p.quantity > 0 ? (
                  <li key={index}>
                    {p.quantity} {p.type}
                  </li>
                ) : null,
              )}
            </ul>
          ) : null}
        </Timeline.Item>
      ))}
    </Timeline>
  )
}
