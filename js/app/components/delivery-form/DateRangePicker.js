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
  const roundedMinutes = Math.ceil(now.minutes() / 5) * 5
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

function generateTimeSlots(afterHour = null) {
  const items = []
  const minutes = [0, 15, 30, 45]

  new Array(24).fill().forEach((_, index) => {
    minutes.forEach(minute => {
      items.push({
        time: moment({ hour: index, minute: minute }),
        disabled: false,
      })
    })
  })

  if (!afterHour) return items

  return items.map(option => {
    const isBefore =
      option.time.hour() > afterHour.hour() ||
      (option.time.hour() === afterHour.hour() &&
        option.time.minute() > afterHour.minute())
    return {
      ...option,
      disabled: !isBefore,
    }
  })
}

const DateTimeRangePicker = ({ format, index }) => {
  const { t } = useTranslation()
  const { values, setFieldValue, errors } = useFormikContext()

  const task = values.tasks[index]

  /** we initialize defaultValues in case we use the switch from timeslots to free picker
   * as we automatically set after and before to null when we use timeslots
   */

  const defaultAfterValue = React.useMemo(() => getNextRoundedTime(), [])
  const defaultBeforeValue = React.useMemo(
    () => defaultAfterValue.clone().add(60, 'minutes'),
    [defaultAfterValue],
  )

  /** we use internal state and then synchronize it with the form values */
  const [afterValue, setAfterValue] = useState(() => {
    const formValue = values.tasks[index].afterValue
    return formValue ? moment(formValue) : defaultAfterValue
  })

  const [beforeValue, setBeforeValue] = useState(() => {
    const formValue = values.tasks[index].beforeValue
    return formValue ? moment(formValue) : defaultBeforeValue
  })

  useEffect(() => {
    setFieldValue(`tasks[${index}].after`, afterValue.toISOString(true))
    setFieldValue(`tasks[${index}].before`, beforeValue.toISOString(true))
  }, [afterValue, beforeValue, index, setFieldValue])

  const [isComplexPicker, setIsComplexPicker] = useState(false)

  const [timeValues, setTimeValues] = useState(() => {
    const after = afterValue || defaultAfterValue
    const before = beforeValue || defaultBeforeValue
    return {
      after: after.format('HH:mm'),
      before: before.format('HH:mm'),
    }
  })

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] = useState([])

  useEffect(() => {
    if (afterValue) {
      const updatedSecondOptions = generateTimeSlots(afterValue)
      setSecondSelectOptions(updatedSecondOptions)
    }

    if (defaultAfterValue) {
      const updatedSecondOptions = generateTimeSlots(defaultAfterValue)
      setSecondSelectOptions(updatedSecondOptions)
    }
  }, [afterValue, defaultAfterValue])

  const handleDateChange = newValue => {
    if (!newValue) return

    const afterHour =
      afterValue?.format('HH:mm:ss') || defaultAfterValue.format('HH:mm:ss')
    const beforeHour =
      beforeValue?.format('HH:mm:ss') || defaultBeforeValue.format('HH:mm:ss')

    const newDate = newValue.format('YYYY-MM-DD')

    setAfterValue(moment(`${newDate} ${afterHour}`))
    setBeforeValue(moment(`${newDate} ${beforeHour}`))
  }

  const handleAfterHourChange = newValue => {
    if (!newValue) return

    const date =
      afterValue?.format('YYYY-MM-DD') || defaultAfterValue.format('YYYY-MM-DD')
    const newAfterHour = moment(`${date} ${newValue}:00`)
    const newBeforeHour = newAfterHour.clone().add(60, 'minutes')

    console.log(newAfterHour, newBeforeHour)
    setTimeValues({
      after: newAfterHour.format('HH:mm'),
      before: newBeforeHour.format('HH:mm'),
    })

    // generate optios for the second picker (beforeValue)
    const afterHour = moment({
      h: newAfterHour.hours(),
      m: newAfterHour.minutes(),
    })
    const updatedSecondOptions = generateTimeSlots(afterHour)
    setSecondSelectOptions(updatedSecondOptions)

    // set the form values for the delivery object
    setAfterValue(newAfterHour)
    setBeforeValue(newBeforeHour)
  }

  const handleBeforeHourChange = newValue => {
    if (!newValue) return

    console.log(newValue)

    setTimeValues(prevState => ({
      ...prevState,
      before: newValue,
    }))
    const date =
      beforeValue?.format('YYYY-MM-DD') ||
      defaultAfterValue.format('YYYY-MM-DD')
    const newBeforeValue = moment(`${date} ${newValue}:00`)
    setBeforeValue(newBeforeValue)
  }

  const handleComplexPickerDateChange = newValues => {
    if (!newValues) return
    setAfterValue(newValues[0])
    setBeforeValue(newValues[1])
  }

  return isComplexPicker ? (
    <>
      {task.type === 'DROPOFF' ? (
        <div className="mb-2 font-weight-bold">Heure de retrait </div>
      ) : (
        <div className="mb-2 font-weight-bold">Heure de dépot</div>
      )}
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <DatePicker.RangePicker
          style={{ width: '95%' }}
          format={'DD MMMM YYYY HH:mm'}
          defaultValue={
            afterValue && beforeValue
              ? [afterValue, beforeValue]
              : [defaultAfterValue, defaultBeforeValue]
          }
          onChange={handleComplexPickerDateChange}
          showTime={{
            ...timePickerProps,
            hideDisabledOptions: true,
          }}
        />
      </div>

      <a
        className="text-secondary"
        title={t('SWITCH_COMPLEX_DATEPICKER')}
        onClick={() => setIsComplexPicker(!isComplexPicker)}>
        {t('SWITCH_COMPLEX_DATEPICKER')}
      </a>
    </>
  ) : (
    <>
      {task.type === 'DROPOFF' ? (
        <div className="mb-2 font-weight-bold">Heure de retrait </div>
      ) : (
        <div className="mb-2 font-weight-bold">Heure de dépot</div>
      )}
      <div className="picker-container">
        <DatePicker
          className="picker-container__datepicker mr-2"
          format={format}
          defaultValue={afterValue || defaultAfterValue}
          onChange={newDate => {
            handleDateChange(newDate)
          }}
        />

        <Select
          className="picker-container__select-left mr-2"
          format={format}
          value={timeValues.after}
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
          value={timeValues.before}
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
      <a
        className="text-secondary"
        title={t('SWITCH_COMPLEX_DATEPICKER')}
        onClick={() => setIsComplexPicker(!isComplexPicker)}>
        {t('SWITCH_COMPLEX_DATEPICKER')}
      </a>
      {errors.tasks?.[index]?.before && (
        <div className="text-danger">{errors.tasks[index].before}</div>
      )}
    </>
  )
}

export default DateTimeRangePicker
