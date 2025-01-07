import React, { useCallback } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import DateRangePicker from '../../../../js/app/delivery/DateRangePicker'
import Packages from '../../../../js/app/delivery/Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from '../../../../js/app/delivery/TotalWeight'


export default ({addresses, storeId, index, storeDeliveryInfos }) => {
  const { t } = useTranslation()
  
  const { values } = useFormikContext()
  const task = values.tasks[index];

  const format = 'LL'

  console.log(storeDeliveryInfos)
  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0
  }, [storeDeliveryInfos])
  
  return (
    <div>
      <h3 className='mb-4'>{task.type === "PICKUP" ? t("DELIVERY_FORM_PICKUP_INFORMATIONS") : t("DELIVERY_FORM_DROPOFF_INFORMATIONS")}</h3>

      <AddressBookNew
        addresses={addresses}
        index={index}
      />

      {task.type === "DROPOFF" ?
        <div>
          <Packages storeId={storeId} index={index}  />
          <TotalWeight index={index} /> 
        </div>
        : null}
        
      {areDefinedTimeSlots() ? (
        <SwitchTimeSlotFreePicker
          storeId={storeId}
          storeDeliveryInfos={storeDeliveryInfos}
          index={index}
          format={format}
        />
      ) : ( 
        <DateRangePicker
          format={format}
          index={index}
        />
      )}
      <div className="mt-4 mb-4">
        <label htmlFor={`tasks[${index}].comments`} className="block mb-2 font-weight-bold">
          {t("ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL")}
        </label>
        <Field
          as={Input.TextArea}
          name={`tasks[${index}].comments`}
          placeholder={t("ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER")}
          rows={4}
          style={{ resize: "none" }}
        />
      </div>
    </div>
  )
}


