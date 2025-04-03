import React, { useEffect, useState } from 'react'

import { useTranslation } from 'react-i18next'

export default ({ distance, tasks }) => {
  const [createdTasks, setCreatedTasks] = useState(null)

  const { t } = useTranslation()

  useEffect(() => {
    const createdTasks = tasks.filter(task => task.address.streetAddress !== '')
    setCreatedTasks(createdTasks)
  }, [tasks])

  return (
    <div className="resume mb-4">
      <div className="resume__distance mt-2 mb-4">
        <span className="font-weight-bold" data-testid="delivery-distance">
          {t('ADMIN_DASHBOARD_DISTANCE', {distance})}
        </span>
      </div>

      <div className="resumer__tasks">
        {createdTasks?.map((task, index) => (
          <div key={index} className="resume__tasks__item mb-3">
            <div>
              {task.type === 'PICKUP' ? (
                <div className="resume__tasks__item__title mb-1 font-weight-bold">
                  <i className="fa fa-arrow-up"></i> {t('DELIVERY_PICKUP')}
                </div>
              ) : (
                <div className="resume__tasks__item__title mb-1 font-weight-bold">
                  <i className="fa fa-arrow-down"></i> {t('DELIVERY_DROPOFF')}
                </div>
              )}
              <div className="resume__tasks__item__address">
                {task.address.streetAddress}
              </div>
              <ul>
                {task.packages?.map((p, index) =>
                  p.quantity > 0 ? (
                    <li key={index} className="resume__tasks__item__packages">
                      {p.quantity} {p.type}
                    </li>
                  ) : null,
                )}
              </ul>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
