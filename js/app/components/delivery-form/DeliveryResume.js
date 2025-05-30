import React, { useEffect, useState } from 'react'

import { useTranslation } from 'react-i18next'
import Itinerary from './Itinerary'

export default ({ tasks }) => {
  const [createdTasks, setCreatedTasks] = useState(null)

  const { t } = useTranslation()

  useEffect(() => {
    const createdTasks = tasks.filter(task => task.address.streetAddress !== '')
    setCreatedTasks(createdTasks)
  }, [tasks])

  return (
    <div className="resume mt-3 pt-3">
      <div className="resumer__tasks">
        {createdTasks ? <Itinerary tasks={createdTasks} withPackages /> : null}
      </div>
    </div>
  )
}
