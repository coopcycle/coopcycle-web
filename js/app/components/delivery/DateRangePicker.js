import React, { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import moment from 'moment'
import { DatePicker, Select } from 'antd'
import { timePickerProps } from '../../utils/antd'

import 'antd/es/input/style/index.css'

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

const DateTimeRangePicker = ({
  defaultValue,
  format,
  afterValue,
  beforeValue,
  setAfterValue,
  setBeforeValue,
}) => {
  const { t } = useTranslation()

  const [isComplexPicker, setIsComplexPicker] = useState(false)

  console.log('after/beforeValue', afterValue, beforeValue)

  // est ce qu'on a toujours besoin de values désormais sachant qu'on les transmet pas au symfony form ? Et qu'on a le after et le before de la tache
  // Par contre il faut sécuriser le fait que si jamais il y a pas de valeur, c'est en effet now et now + 15
  const [values, setValues] = useState(() =>
    defaultValue
      ? [moment(defaultValue.after), moment(defaultValue.before)]
      : [moment(), moment().add(15, 'minutes')],
  )

  const [timeValues, setTimeValues] = useState(
    afterValue && beforeValue
      ? {
          after: moment(afterValue).format('HH:mm'),
          before: moment(beforeValue).format('HH:mm'),
        }
      : {},
  )

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] = useState([])
  // à réécrire, les seconds time slots sont générés à partir de after reçu
  useEffect(() => {
    if (afterValue) {
      const updatedSecondOptions = generateTimeSlots(afterValue)
      setSecondSelectOptions(updatedSecondOptions)
    }
  }, [afterValue])
  // ici on vient récupérer les valeurs de after et before passés en props et on vient modifier également, on passe plus par value
  const handleDateChange = newValue => {
    if (!newValue) return

    const afterHour = afterValue.format('HH:mm:ss')
    const beforeHour = beforeValue.format('HH:mm:ss')

    const newDate = newValue.format('YYYY-MM-DD')

    setAfterValue(moment.utc(`${newDate} ${afterHour}`))
    setBeforeValue(moment.utc(`${newDate} ${beforeHour}`))
  }
  // pareil ici, mais on garde la logique de mettre à jour les time values
  const handleAfterHourChange = newValue => {
    if (!newValue) return

    const date = afterValue.format('YYYY-MM-DD')
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
  // idem ici
  const handleBeforeHourChange = newValue => {
    if (!newValue) return

    setTimeValues(prevState => ({
      ...prevState,
      before: newValue,
    }))
    const date = values[0].format('YYYY-MM-DD')
    const beforeValue = moment(`${date} ${newValue}:00`)
    setBeforeValue(beforeValue)
  }
  // on va devoir le réécrire pour coller avec la logique du after et before et séparer les deux valeurs rendues
  const handleComplexPickerDateChange = newValue => {
    if (!newValue) return
    console.log(newValue)
  }

  // const isFirstRender = useRef(true)

  // useEffect(() => {
  //   if (isFirstRender.current) {
  //     isFirstRender.current = false
  //     return
  //   }

  //   onChange({ after: values[0], before: values[1] })
  // }, [values, onChange])

  return isComplexPicker ? (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <DatePicker.RangePicker
          style={{ width: '95%' }}
          format={format}
          defaultValue={values}
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
            defaultValue={values[0]}
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
