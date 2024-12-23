import React, { useState, useCallback, useEffect } from 'react'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'
import moment from 'moment'
import { useTranslation } from 'react-i18next'


export default ({task, addresses, storeId, storeDeliveryInfos, onUpdate }) => {
  const { t } = useTranslation()
  
  const [afterValue, setAfterValue] = useState(moment(task?.afterValue))
  const [beforeValue, setBeforeValue] = useState(moment(task?.beforeValue))
  const [timeSlot, setTimeSlotValue] = useState(task?.timeSlot)
  const [commentary, setCommentary] = useState(task?.commentary)
  const [deliveryAddress, setDeliveryAddress] = useState(task?.address)
  const [toBeModified, setToBeModified] = useState(task?.toBeModified)
  const [toBeRemembered, setToBeRemembered] = useState(task?.toBeRemembered)
  const format = 'LL'
  
    useEffect(() => {
      onUpdate({
      afterValue : afterValue?.toISOString(),
      beforeValue : beforeValue?.toISOString(),
      timeSlot,
      commentary,
      address: deliveryAddress,
      toBeModified,
      toBeRemembered
    });
    }, [afterValue, beforeValue, timeSlot, commentary, deliveryAddress, toBeModified, toBeRemembered]);
  
  
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
                setToBeModified={setToBeModified}
        setToBeRemembered={setToBeRemembered}
        toBeModified={toBeModified}
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
                    placeholder={t("ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER")}
                    onChange={e => setCommentary(e.target.value)}
                    rows={4}
                    style={{ resize: "none" }}
                  />
                </div>
    </div>
  )
}


