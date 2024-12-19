import React from 'react'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ storeId }) => {
  // const [values, setValues] = useState([])


  return (
    <>
    <div>Switch</div>
    <TimeSlotPicker 
        storeId={storeId} />
      </>
  )
}