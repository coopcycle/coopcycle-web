import React, { useState, useCallback, useEffect } from 'react'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'

export default ({task, addresses, storeId, storeDeliveryInfos, onUpdate }) => {

  const [afterValue, setAfterValue] = useState(task?.afterValue)
  const [beforeValue, setBeforeValue] = useState(task?.beforeValue)
  const [timeSlot, setTimeSlotValue] = useState(task?.timeSlot)
  const [commentary, setCommentary] = useState(task?.commentary)
  const [deliveryAddress, setDeliveryAddress] = useState(task?.deliveryAddress)
  const format = 'LL'
  
    useEffect(() => {
      onUpdate({
      afterValue,
      beforeValue,
      timeSlot,
      commentary,
      deliveryAddress,
    });
    }, [afterValue, beforeValue, timeSlot, commentary, deliveryAddress]);
  
  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0;
  }, [storeDeliveryInfos])
  
  const { TextArea } = Input

  return (
    <div>
      <h2>Informations du {task.type === "pickup" ? "Retrait" : "Dépot"}</h2>
              <AddressBookNew
                addresses={addresses}
                deliveryAddress={deliveryAddress}
                setDeliveryAddress={setDeliveryAddress}
              />
              {areDefinedTimeSlots() ?
                <SwitchTimeSlotFreePicker
                  storeId={storeId}
                  storeDeliveryInfos={storeDeliveryInfos}
                  setTimeSlotValue={setTimeSlotValue}
                  format={format}
                  afterValue={afterValue}
                  beforeValue={beforeValue}
                  setAfterValue={setAfterValue}
                  setBeforeValue={setBeforeValue}
                /> :
                <DateRangePicker
                  format={format}
                  afterValue={afterValue}
                  beforeValue={beforeValue}
                  setAfterValue={setAfterValue}
                  setBeforeValue={setBeforeValue}
                />}

                <div className="mt-4">
                  <label htmlFor="commentary" className="block mb-2">
                    Commentaire
                  </label>
                  <TextArea
                    value={commentary}
                    onChange={e => setCommentary(e.target.value)}
                    rows={4}
                    style={{ resize: "none" }}
                  />
                </div>
    </div>
  )
}


