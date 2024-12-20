import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import TimeSlotPicker from '../../../../js/app/components/delivery/TimeSlotPicker'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'

export default ({ storeId, storeDeliveryInfos, setTimeSlotValue, format, afterValue, beforeValue, setAfterValue, setBeforeValue }) => {
  const {t} = useTranslation()
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)



  useEffect(() => {
    if (isTimeSlotSelect) {
      setBeforeValue(null)
      setAfterValue(null)
    } else {
      setTimeSlotValue(null)
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
            setTimeSlotValue={setTimeSlotValue}
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
                  afterValue={afterValue}
                  beforeValue={beforeValue}
                  setAfterValue={setAfterValue}
                  setBeforeValue={setBeforeValue}
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
    </>
  )
}

