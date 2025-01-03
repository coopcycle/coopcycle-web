import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'
import { useFormikContext } from 'formik'

export default ({ storeId, storeDeliveryInfos, index, format }) => {
  const {t} = useTranslation()
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)

  const {setFieldValue, errors} = useFormikContext()

  useEffect(() => {
    if  (isTimeSlotSelect && storeDeliveryInfos.timeSlots.length > 0)  {
      setFieldValue(`tasks[${index}].doneAfter`, null)
      setFieldValue(`tasks[${index}].doneBefore`, null)
    } else {
      setFieldValue(`tasks[${index}].timeSlot`, null)
    }
  }, [isTimeSlotSelect])
  
  return (
    <>
      {isTimeSlotSelect ? (
        <div
          style={{
            display: 'flex',
            alignItems: 'flex-end',
          }}>
          <div style={{ width: '95%' }}>
            <TimeSlotPicker
            storeId={storeId}
            storeDeliveryInfos={storeDeliveryInfos}
            index={index}
            />
          </div>
          <i
            className="fa fa-calendar"
            aria-hidden="true"
            onClick={() => setIsTimeSlotSelect(!isTimeSlotSelect)}
            style={{
              cursor: 'pointer',
              color: '#24537D',
              width: '5%',
              lineHeight: '32px',
            }}
            title={t('SWITCH_TIMESLOTPICKER')}></i>
        </div>
      ) : (
        <div style={{ display: 'flex', alignItems: 'baseline' }}>
          <div style={{ width: '95%' }}>
            <DateRangePicker
                format={format}
                index={index}
            />
          </div>
          <i
            className="fa fa-calendar"
            aria-hidden="true"
            onClick={() => setIsTimeSlotSelect(!isTimeSlotSelect)}
            style={{ cursor: 'pointer', color: '#24537D', width: '5%' }}
            title={t('SWITCH_DATEPICKER')}></i>
        </div>
      )}
      {errors.tasks?.[index]?.doneBefore && (
        <div className="text-danger">{errors.tasks[index].doneBefore}</div>
      )}
    </>
  )
}

