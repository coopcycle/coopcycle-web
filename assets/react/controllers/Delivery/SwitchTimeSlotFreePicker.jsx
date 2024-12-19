import React, { useCallback } from 'react'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ storeId, storeDeliveryInfos }) => {
  /** if time */
  // const [values, setValues] = useState({})

  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0;
  }, [storeDeliveryInfos])

 

  console.log(storeDeliveryInfos)

  return (
    <>
      <div>Switch</div>
      {areDefinedTimeSlots()  ?
          (<TimeSlotPicker 
            storeId={storeId}
          storeDeliveryInfos={storeDeliveryInfos}
        />)
        : <div>Pas de timeslots</div>}
      </>
  )
}