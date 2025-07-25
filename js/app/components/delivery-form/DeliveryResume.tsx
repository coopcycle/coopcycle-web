import React, { useEffect, useState } from 'react'

import { useTranslation } from 'react-i18next'
import Itinerary from '../DeliveryItinerary'
import { Task } from './types'

type Props = {
  tasks: Task[]
}

const DeliveryResume = ({ tasks }: Props) => {
  const [createdTasks, setCreatedTasks] = useState<Task[] | null>(null)

  const { t } = useTranslation()

  useEffect(() => {
    const createdTasks = tasks.filter(task => task.address.streetAddress !== '')
    setCreatedTasks(createdTasks)
  }, [tasks])

  return (
    <div className="resume mt-3 pt-3">
      {createdTasks ? <Itinerary tasks={createdTasks} withPackages /> : null}
    </div>
  )
}

export default DeliveryResume
