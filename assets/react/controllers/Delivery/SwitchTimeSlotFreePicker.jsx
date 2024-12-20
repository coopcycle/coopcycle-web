import React from 'react'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ storeId, storeDeliveryInfos, setTimeSlotValue }) => {

  return (
    <>
      <div>Switch</div>
          <TimeSlotPicker 
            storeId={storeId}
          storeDeliveryInfos={storeDeliveryInfos}
          setTimeSlotValue={setTimeSlotValue}
        />
      </>
  )
}