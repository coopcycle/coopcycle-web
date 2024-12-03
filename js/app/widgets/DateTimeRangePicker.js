import React, { useState, useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd'
import { timePickerProps } from '../utils/antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

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

  const secondSelectOptions = items.map(option => {
    const isBefore = afterHour.isBefore(option.time)
    return {
      ...option,
      disabled: !isBefore,
    }
  })

  return secondSelectOptions
}

const DateTimeRangePicker = ({ defaultValue, onChange, format, showTime }) => {
  const { t } = useTranslation()

  const [isComplexPicker, setIsComplexPicker] = useState(false)

  const [values, setValues] = useState(
    defaultValue
      ? [moment(defaultValue.after), moment(defaultValue.before)]
      : [],
  )

  const [timeValues, setTimeValues] = useState(
    defaultValue
      ? {
          after: moment(defaultValue.after).format('HH:mm'),
          before: moment(defaultValue.before).format('HH:mm'),
        }
      : {},
  )

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] = useState(
    generateTimeSlots(values[0]),
  )

  const handleDateChange = newValue => {
    if (!newValue) return

    const afterHour = values[0].format('HH:mm:ss')
    const beforeHour = values[1].format('HH:mm:ss')

    const newDate = newValue.format('YYYY-MM-DD')

    setValues([
      moment(`${newDate} ${afterHour}`),
      moment(`${newDate} ${beforeHour}`),
    ])
  }

  const handleAfterHourChange = newValue => {
    if (!newValue) return

    const date = values[0].format('YYYY-MM-DD')
    const afterValue = moment(`${date} ${newValue}:00`)
    const beforeValue = afterValue.clone().add(15, 'minutes')

    setTimeValues({
      after: afterValue.format('HH:mm'),
      before: beforeValue.format('HH:mm'),
    })

    const afterHour = moment({
      h: afterValue.hours(),
      m: afterValue.minutes(),
    })

    const updatedSecondOptions = generateTimeSlots(afterHour)
    setSecondSelectOptions(updatedSecondOptions)

    setValues(prevArray => {
      const newValues = [...prevArray]
      newValues[0] = afterValue
      newValues[1] = beforeValue
      return newValues
    })
  }

  const handleBeforeHourChange = newValue => {
    if (!newValue) return

    setTimeValues(prevState => ({
      ...prevState,
      before: newValue,
    }))
    const date = values[0].format('YYYY-MM-DD')
    const beforeValue = moment(`${date} ${newValue}:00`)
    setValues(prevArray => {
      const newValues = [...prevArray]
      newValues[1] = beforeValue
      return newValues
    })
  }

  const handleComplexPickerDateChange = newValue => {
    if (!newValue) return
    setValues(newValue)
  }

  let props = {}
  if (showTime) {
    props = {
      showTime: {
        ...timePickerProps,
        hideDisabledOptions: true,
      },
    }
  }

  const isFirstRender = useRef(true)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }

    onChange({ after: values[0], before: values[1] })
  }, [values, onChange])

  return isComplexPicker ? (
    <>
      <DatePicker.RangePicker
        style={{ width: '96%' }}
        format={format}
        defaultValue={values}
        onChange={handleComplexPickerDateChange}
        {...props}
      />
      <a title={t('SWITCH_COMPLEX_DATEPICKER')}>
        <i
          className="fa fa-calendar pr-1"
          style={{ marginLeft: '0.1em' }}
          onClick={() => setIsComplexPicker(!isComplexPicker)}
        />
      </a>
    </>
  ) : (
    <>
      <DatePicker
        style={{ width: '50%' }}
        format="LL"
        defaultValue={values[0]}
        onChange={newDate => {
          handleDateChange(newDate)
        }}
      />

      <Select
        style={{ width: '20%' }}
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
        style={{ width: '20%' }}
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
      <a title="Sélectionner une date sur plusieurs jours">
        <i
          className="fa fa-calendar pr-1"
          style={{ marginLeft: '0.5em' }}
          onClick={() => setIsComplexPicker(!isComplexPicker)}
        />
      </a>
    </>
  )
}

export default function (el, options) {
  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {},
    format: 'LLL',
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={antdLocale}>
      <DateTimeRangePicker {...props} />
    </ConfigProvider>,
    el,
  )
}
