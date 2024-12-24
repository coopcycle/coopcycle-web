import React, { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import moment from 'moment'
import { DatePicker, Select } from 'antd'
import { timePickerProps } from '../../utils/antd'
import { useFormikContext } from 'formik'

import 'antd/es/input/style/index.css'

function getNextRoundedTime() {
  const now = moment()
  now.add(15, 'minutes')
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
  const { values, setFieldValue } = useFormikContext()

  /** we initialize defaultValues in case we use the switch from timeslots to free picker
   * as we automatically set after and before to null when we use timeslots
   */

  const defaultAfterValue = React.useMemo(() => getNextRoundedTime(), [])
  const defaultBeforeValue = React.useMemo(
    () => defaultAfterValue.clone().add(15, 'minutes'),
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
    setFieldValue(`tasks[${index}].afterValue`, afterValue.toISOString())
    setFieldValue(`tasks[${index}].beforeValue`, beforeValue.toISOString())
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
    const newBeforeHour = newAfterHour.clone().add(15, 'minutes')

    setTimeValues({
      after: newAfterHour.format('HH:mm'),
      before: newBeforeHour.format('HH:mm'),
    })

    const afterHour = moment({
      h: newAfterHour.hours(),
      m: newAfterHour.minutes(),
    })

    const updatedSecondOptions = generateTimeSlots(afterHour)
    setSecondSelectOptions(updatedSecondOptions)

    setAfterValue(newAfterHour)
  }

  const handleBeforeHourChange = newValue => {
    if (!newValue) return

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
    setBeforeValue(newValues[0])
    setAfterValue(newValues[1])
  }

  return isComplexPicker ? (
    <>
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
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <div style={{ width: '95%' }}>
          <DatePicker
            style={{ width: '50%' }}
            format={format}
            defaultValue={afterValue || defaultAfterValue}
            onChange={newDate => {
              handleDateChange(newDate)
            }}
          />

          <Select
            style={{ width: '25%' }}
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
            style={{ width: '25%' }}
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
      </div>
      <a
        className="text-secondary"
        title={t('SWITCH_COMPLEX_DATEPICKER')}
        onClick={() => setIsComplexPicker(!isComplexPicker)}>
        {t('SWITCH_COMPLEX_DATEPICKER')}
      </a>
    </>
  )
}

export default DateTimeRangePicker
