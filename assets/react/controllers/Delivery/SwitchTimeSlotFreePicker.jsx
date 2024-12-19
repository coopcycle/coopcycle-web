import React, { useEffect, useState } from 'react'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ timeSlotsOptions, defaultTimeSlotName, storeId }) => {
  // const [values, setValues] = useState([])
  const [initialTimeSlotChoices, setInitialChoices] = useState({})
  useEffect(() => {
    const initialChoices = timeSlotsOptions[defaultTimeSlotName]
    setInitialChoices(initialChoices)
  }, [timeSlotsOptions, defaultTimeSlotName])

  return (
    <>
    <div>Switch</div>
    <TimeSlotPicker 
        choices={timeSlotsOptions}
        initialChoices={initialTimeSlotChoices}
        defaultTimeSlotName={defaultTimeSlotName}
        storeId={storeId} />
      
      </>
  )
}