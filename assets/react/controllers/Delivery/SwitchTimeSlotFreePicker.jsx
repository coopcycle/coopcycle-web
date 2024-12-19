import React, { useCallback } from 'react'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'

export default ({ storeId, storeDeliveryInfos, setTimeSlotValue }) => {
  /** if time */
  // const [values, setValues] = useState({})

  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0;
  }, [storeDeliveryInfos])

  // on vient ici initialiser before et after ? et si jamais on a choisi des timeslots ? on peut passer aussi le before et after si choisit de switcher ? 

  // ou alors on a un state avec timeslot, before et after : si timeslot : on crée un after et before
  // dans le cas où on veut switch, sinon on a simplement after et before
  // et c'est le pickup qui a dans son state le timevalue ? -> pickup représente une tache
  // donc on doit passer une partie des infos (adresse etc dans pickup)
 

  console.log(storeDeliveryInfos)

  return (
    <>
      <div>Switch</div>
      {areDefinedTimeSlots()  ?
          (<TimeSlotPicker 
            storeId={storeId}
          storeDeliveryInfos={storeDeliveryInfos}
          setTimeSlotValue={setTimeSlotValue}
        />)
        : <div>Pas de timeslots</div>}
      </>
  )
}