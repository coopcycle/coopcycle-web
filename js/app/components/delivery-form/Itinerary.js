import React from 'react'
import { Timeline } from 'antd'
import { useTranslation } from 'react-i18next'
import { taskTypeColor, taskTypeListIcon } from '../../styles'

const Dot = ({ type }) => {
  return (
    <i
      className={`fa ${taskTypeListIcon(type)}`}
      style={{ color: taskTypeColor(type) }}
    />
  )
}

export default ({ tasks, withPackages = false }) => {
  const { t } = useTranslation()

  return (
    <Timeline>
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
          </div>
          {task.address?.streetAddress ? (
            <div>{task.address?.streetAddress}</div>
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
