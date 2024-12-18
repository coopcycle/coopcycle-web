import React, { useEffect, useState } from 'react'
// import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ timeSlotsOptions, defaultTimeSlotName }) => {
  // const [values, setValues] = useState([])
  const [initialTimeSlotChoices, setInitialChoices] = useState({})
  useEffect(() => {
    const initialChoices = {}
    initialChoices[defaultTimeSlotName] = timeSlotsOptions[defaultTimeSlotName]
    setInitialChoices(initialChoices)
  }, [timeSlotsOptions, defaultTimeSlotName])

  console.log("ici", timeSlotsOptions, defaultTimeSlotName, initialChoices)
  return (
    <>
    <div>Switch</div>
    <TimeSlotPicker 
        choices={timeSlotsOptions} 
        initialChoices={initialTimeSlotChoices}/>
      </>
  )
}