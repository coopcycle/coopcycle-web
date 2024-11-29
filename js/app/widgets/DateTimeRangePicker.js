import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const { Option } = Select

function generateTimeSlots(disabled = false) {
  const items = []
  new Array(24).fill().forEach((acc, index) => {
    items.push({
      time: moment({ hour: index }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 15 }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 30 }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 45 }),
      disabled,
    })
  })
  return items
}

const DateTimeRangePicker = ({ defaultValue, onChange, format }) => {
  const [value, setValue] = useState(() =>
    defaultValue
      ? [moment(defaultValue.after), moment(defaultValue.before)]
      : [],
  )

  const [firstSelectOptions, setFirstSelectOptions] =
    useState(generateTimeSlots())
  const [secondSelectOptions, setSecondSelectOptions] =
    useState(generateTimeSlots())

  const [timeValues, setTimeValues] = useState(
    defaultValue
      ? {
          after: moment(defaultValue.after).format('HH:mm'),
          before: moment(defaultValue.before).format('HH:mm'),
        }
      : {},
  )

  useEffect(() => {
    console.log('timeValues.after dans useEffect :', timeValues.after)
    console.log('secondSelectOptions avant modification :', secondSelectOptions)

    const after = moment(timeValues.after, 'HH:mm')
    console.log('after en objet moment :', after)

    const before = after.clone().add(15, 'minutes')
    setTimeValues(prevState => ({
      ...prevState,
      before: before.format('HH:mm'),
    }))
    console.log('before', before)

    const updatedSecondOptions = secondSelectOptions.map(option => {
      const isBefore = after.isBefore(option.time)
      return {
        ...option,
        disabled: !isBefore,
      }
    })

    console.log('secondSelectOptions mis à jour :', updatedSecondOptions)
    setSecondSelectOptions(updatedSecondOptions)
  }, [timeValues.after])

  const handleChange = ({ type, newValue }) => {
    if (!newValue) return // est ce que c'est pas redondant avec le return du switch ?

    let afterValue = value[0]
    let beforeValue = value[1]

    switch (type) {
      case 'date':
        const afterHour = afterValue.format('HH:mm:ss')
        const beforeHour = beforeValue.format('HH:mm:ss')

        const newDate = newValue.format('YYYY-MM-DD')

        afterValue = moment(`${newDate} ${afterHour}`)
        beforeValue = moment(`${newDate} ${beforeHour}`)
        setValue([afterValue, beforeValue])
        break
      case 'afterHour':
        // on vient gérer les heures pour les comparer
        console.log('newValue', newValue)
        const newAfter = moment(newValue, 'HH:mm')
        console.log('newAfter', newAfter)
        const date = afterValue.format('YYYY-MM-DD')
        afterValue = moment(`${date} ${newValue}:00`)
        setValue([afterValue, beforeValue])

        break
      case 'beforeHour':
        const oldDate = afterValue.format('YYYY-MM-DD')
        beforeValue = moment(`${oldDate} ${newValue}:00`)
        setValue([afterValue, beforeValue])
        break

      default:
        return
    }

    const isBefore = moment(afterValue).isBefore(beforeValue)

    if (!isBefore) {
      console.log('la première heure est après la seconde')
      console.log(isBefore)
    } else {
      onChange({
        after: afterValue,
        before: beforeValue,
      })
    }
  }

  return (
    <>
      <DatePicker
        style={{ width: '50%' }}
        format="LL"
        defaultValue={value[0]}
        onChange={newDate => {
          handleChange({ type: 'date', newValue: newDate })
        }}
      />
      <Select
        style={{ width: '25%' }}
        format={format}
        // defaultValue={value[0].format('HH:mm')}
        // defaultValue={timeValues.after}
        value={timeValues.after}
        onChange={newAfterHour => {
          handleChange({ type: 'afterHour', newValue: newAfterHour })
          setTimeValues(prevState => ({
            ...prevState,
            after: newAfterHour,
          }))
          // setTimeValues({ after: newAfterHour, before: timeValues.before }) // voir si c'est un pb que la deuxième valeur devienne vide ou si on met par défaut 1h après
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
        // defaultValue={value[1].format('HH:mm')}
        value={timeValues.before}
        onChange={newBeforeHour => {
          handleChange({ type: 'beforeHour', newValue: newBeforeHour })
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
    </>
  )
}

export default function (el, options) {
  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {},
    format: 'LLL', // verifier ce qu'on met là
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={antdLocale}>
      <DateTimeRangePicker {...props} />
    </ConfigProvider>,
    el,
  )
}
