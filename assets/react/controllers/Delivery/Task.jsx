import React, { useState, useCallback, useEffect } from 'react'
import { useFormikContext, Field } from 'formik';
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'
import moment from 'moment'
import { useTranslation } from 'react-i18next'


export default ({addresses, storeId, index, storeDeliveryInfos }) => {
  const { t } = useTranslation()
  
  const { values, setFieldValue } = useFormikContext();
  const task = values.tasks[index];

  const format = 'LL'
  
    // useEffect(() => {
    //   onUpdate({
    //   afterValue : afterValue?.toISOString(),
    //   beforeValue : beforeValue?.toISOString(),
    //   timeSlot,
    //   commentary,
    //   address: deliveryAddress,
    //   toBeModified,
    //   toBeRemembered
    // });
    // }, [afterValue, beforeValue, timeSlot, commentary, deliveryAddress, toBeModified, toBeRemembered]);
  
  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0;
  }, [storeDeliveryInfos])
  
  const { TextArea } = Input

  return (
    // <div>
    //   <h2>Informations du {task.type === "pickup" ? "Retrait" : "Dépot"}</h2>
    //           <AddressBookNew
    //             addresses={addresses}
    //             deliveryAddress={deliveryAddress}
    //             setDeliveryAddress={setDeliveryAddress}
    //             setToBeModified={setToBeModified}
    //     setToBeRemembered={setToBeRemembered}
    //     toBeModified={toBeModified}
    //           />
    //           {areDefinedTimeSlots() ?
    //             <SwitchTimeSlotFreePicker
    //               storeId={storeId}
    //               storeDeliveryInfos={storeDeliveryInfos}
    //               setTimeSlotValue={setTimeSlotValue}
    //               format={format}
    //               afterValue={afterValue}
    //               beforeValue={beforeValue}
    //               setAfterValue={setAfterValue}
    //               setBeforeValue={setBeforeValue}
    //             /> :
    //             <DateRangePicker
    //               format={format}
    //               afterValue={afterValue}
    //               beforeValue={beforeValue}
    //               setAfterValue={setAfterValue}
    //               setBeforeValue={setBeforeValue}
    //             />}

    //             <div className="mt-4">
    //               <label htmlFor="commentary" className="block mb-2">
    //                 Commentaire
    //               </label>
    //               <TextArea
    //                 value={commentary}
    //                 placeholder={t("ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER")}
    //                 onChange={e => setCommentary(e.target.value)}
    //                 rows={4}
    //                 style={{ resize: "none" }}
    //               />
    //             </div>
    // </div>
        <div>
      <h2>Informations du {task.type === "pickup" ? "Retrait" : "Dépot"}</h2>
      <AddressBookNew
        addresses={addresses}
        index={index}
      />
      {areDefinedTimeSlots() ? (
        <SwitchTimeSlotFreePicker
          storeId={storeId}
          storeDeliveryInfos={storeDeliveryInfos}
          index={index}
          setTimeSlotValue={(value) => setFieldValue(`tasks[${index}].timeSlot`, value)}
          format={format}
          afterValue={moment(task.afterValue)}
          beforeValue={moment(task.beforeValue)}
          setAfterValue={(value) => setFieldValue(`tasks[${index}].afterValue`, value.toISOString())}
          setBeforeValue={(value) => setFieldValue(`tasks[${index}].beforeValue`, value.toISOString())}
        />
      ) : ( null
        // <DateRangePicker
        //   format={format}
        //   afterValue={moment(task.afterValue)}
        //   beforeValue={moment(task.beforeValue)}
        //   setAfterValue={(value) => setFieldValue(`tasks[${index}].afterValue`, value.toISOString())}
        //   setBeforeValue={(value) => setFieldValue(`tasks[${index}].beforeValue`, value.toISOString())}
        // />
      )}
      <div className="mt-4">
        <label htmlFor={`tasks[${index}].commentary`} className="block mb-2">
          Commentaire
        </label>
        <Field
          as={Input.TextArea}
          name={`tasks[${index}].commentary`}
          placeholder={t("ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER")}
          rows={4}
          style={{ resize: "none" }}
        />
      </div>
    </div>
  )
}


