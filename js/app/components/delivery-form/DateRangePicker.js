import React, { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import moment from 'moment'
import { DatePicker, Select } from 'antd'
import { timePickerProps } from '../../utils/antd'
import { useFormikContext } from 'formik'

import './DateRangePicker.scss'

function getNextRoundedTime() {
  const now = moment()
  now.add(60, 'minutes')
  const roundedMinutes = Math.ceil(now.minutes() / 10) * 10
  if (roundedMinutes >= 60) {
    now.add(1, 'hour')
    now.minutes(roundedMinutes - 60)
  } else {
    now.minutes(roundedMinutes)
  }
  now.seconds(0)

  return now
}

const { Option } = Select

function generateTimeSlots(after = null) {
  const items = []
  const minutes = [0, 10, 20, 30, 40, 50]

  new Array(24).fill().forEach((_, index) => {
    minutes.forEach(minute => {
      items.push({
        time: moment({ hour: index, minute: minute }),
        disabled: false,
      })
    })
  })

  if (!after) { return items }

  return items.map(option => {
    const isBefore =
      option.time.hour() > after.hour() ||
      (option.time.hour() === after.hour() &&
        option.time.minute() > after.minute())
    return {
      ...option,
      disabled: !isBefore,
    }
  })
}

const DateTimeRangePicker = ({ format, index, isDispatcher }) => {
  const { t } = useTranslation()
  const { values, setFieldValue, errors } = useFormikContext()

  const task = values.tasks[index]

  useEffect(() => { 
    if (!values.tasks[index].after && !values.tasks[index].before) {
        const after = getNextRoundedTime()
        const before = after.clone().add(10, 'minutes')
        setFieldValue(`tasks[${index}].after`, after.toISOString(true))
        setFieldValue(`tasks[${index}].before`, before.toISOString(true))
      }
    },
    [values.tasks[index].after, values.tasks[index].before]
  )

  const [isComplexPicker, setIsComplexPicker] = useState(
    moment(values.tasks[index].after).isBefore(
      values.tasks[index].before,
      'day',
    ),
  )

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] = useState([])


  useEffect(() => {
    if (values.tasks[index].after) {
      setSecondSelectOptions(generateTimeSlots(moment(values.tasks[index].after)))
    }
  }, [values.tasks[index].after])

  const handleDateChange = newValue => {
    const afterHour = moment(values.tasks[index].after).format('HH:mm:ss')
    const beforeHour = moment(values.tasks[index].before).format('HH:mm:ss')
    const newDate = newValue.format('YYYY-MM-DD')

    setFieldValue(`tasks[${index}].after`, moment(`${newDate} ${afterHour}`).toISOString(true))
    setFieldValue(`tasks[${index}].before`, moment(`${newDate} ${beforeHour}`).toISOString(true))
  }

  const handleAfterHourChange = newValue => {
    const date = moment(values.tasks[index].after).format('YYYY-MM-DD')
    const newAfter = moment(`${date} ${newValue}:00`)
    const newBefore = newAfter.clone().add(10, 'minutes')

    setFieldValue(`tasks[${index}].after`, newAfter.toISOString(true))
    setFieldValue(`tasks[${index}].before`, newBefore.toISOString(true))
  }

  const handleBeforeHourChange = newValue => {
    const date = moment(values.tasks[index].after).format('YYYY-MM-DD')
    const newBefore = moment(`${date} ${newValue}:00`)
    setFieldValue(`tasks[${index}].before`, newBefore.toISOString(true))
  }

  const handleComplexPickerDateChange = newValues => {
    setFieldValue(`tasks[${index}].after`, newValues[0].toISOString(true))
    setFieldValue(`tasks[${index}].before`, newValues[1].toISOString(true))
  }

  // When we switch back to simple picker, we need to set back after and before at the same day
  const handleSwitchComplexAndSimplePicker = () => {
    if (isComplexPicker === true) {
      const before = moment(values.tasks[index].after).clone().add(1, 'hours')
      setFieldValue(`tasks[${index}].before`, before.toISOString(true))
    }
    setIsComplexPicker(!isComplexPicker)
  }

  return isComplexPicker ? (
    <>
      {task.type === 'DROPOFF' ? (
        <div className="mb-2 font-weight-bold">
          {t('DELIVERY_FORM_DROPOFF_HOUR')}
        </div>
      ) : (
        <div className="mb-2 font-weight-bold">
          {t('DELIVERY_FORM_PICKUP_HOUR')}
        </div>
      )}
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <DatePicker.RangePicker
          style={{ width: '95%' }}
          format={'DD MMMM YYYY HH:mm'}
          // defaultValue={
          //   afterValue && beforeValue
          //     ? [afterValue, beforeValue]
          //     : [defaultAfterValue, defaultBeforeValue]
          // }
          value={[moment(values.tasks[index].after), moment(values.tasks[index].before)]}
          onChange={handleComplexPickerDateChange}
          showTime={{
            ...timePickerProps,
            hideDisabledOptions: true,
          }}
        />
      </div>

      {isDispatcher && (
        <a
          className="text-secondary"
          title={t('SWITCH_COMPLEX_DATEPICKER')}
          onClick={handleSwitchComplexAndSimplePicker}>
          {t('SWITCH_COMPLEX_DATEPICKER')}
        </a>
      )}
    </>
  ) : (
    <>
      {task.type === 'DROPOFF' ? (
        <div className="mb-2 font-weight-bold">
          {t('DELIVERY_FORM_DROPOFF_HOUR')}
        </div>
      ) : (
        <div className="mb-2 font-weight-bold">
          {t('DELIVERY_FORM_PICKUP_HOUR')}
        </div>
      )}
      <div className="picker-container">
        <DatePicker
          className="picker-container__datepicker mr-2"
          format={format}
          // defaultValue={afterValue || defaultAfterValue}
          value={moment(values.tasks[index].after)}
          onChange={newDate => {
            handleDateChange(newDate)
          }}
        />

        <Select
          className="picker-container__select-left mr-2"
          format={format}
          value={moment(values.tasks[index].after).format('HH:mm')}
          onChange={newAfterHour => {
            handleAfterHourChange(newAfterHour)
          }}>
          {firstSelectOptions.map(option => (
            <Option
              key={option.time.format('HH:mm')}
              value={option.time.format('HH:mm')}
              disabled={option.disabled}>
              {option.time.format('HH:mm')}
            </Option>
          ))}
        </Select>

        <Select
          className="picker-container__select-right"
          format={format}
          value={moment(values.tasks[index].before).format('HH:mm')}
          onChange={newBeforeHour => {
            handleBeforeHourChange(newBeforeHour)
          }}>
          {secondSelectOptions.map(option => (
            <Option
              key={option.time.format('HH:mm')}
              value={option.time.format('HH:mm')}
              disabled={option.disabled}>
              {option.time.format('HH:mm')}
            </Option>
          ))}
        </Select>
      </div>
      {isDispatcher && (
        <a
          className="text-secondary"
          title={t('SWITCH_COMPLEX_DATEPICKER')}
          onClick={() => setIsComplexPicker(!isComplexPicker)}>
          {t('SWITCH_COMPLEX_DATEPICKER')}
        </a>
      )}
      {errors.tasks?.[index]?.before && (
        <div className="text-danger">{errors.tasks[index].before}</div>
      )}
    </>
  )
}

export default DateTimeRangePicker
