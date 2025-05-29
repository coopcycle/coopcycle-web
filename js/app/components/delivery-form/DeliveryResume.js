import React, { useEffect, useState } from 'react'

import { useTranslation } from 'react-i18next'
import Itinerary from './Itinerary'

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
          {t('ADMIN_DASHBOARD_DISTANCE', { distance })}
        </span>
      </div>

      <div className="resumer__tasks">
        {createdTasks ? <Itinerary tasks={createdTasks} withPackages /> : null}
      </div>
    </div>
  )
}
