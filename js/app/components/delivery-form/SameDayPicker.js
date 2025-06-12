import React, { useEffect, useState } from 'react'
import moment from 'moment/moment'
import { DatePicker, Select } from 'antd'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'
import { Mode } from './mode'

const { Option } = Select

function generateTimeSlots(after = null) {
  const items = []
  const minutes = [0, 10, 20, 30, 40, 50]

  new Array(24).fill().forEach((_, taskIndex) => {
    minutes.forEach(minute => {
      items.push({
        time: moment({ hour: taskIndex, minute: minute }),
        disabled: false,
      })
    })
  })

  if (!after) {
    return items
  }

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

const SameDayPicker = ({ format, taskIndex }) => {
  const mode = useSelector(selectMode)
  const { taskValues, setFieldValue, errors } = useDeliveryFormFormikContext({
    taskIndex: taskIndex,
  })

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] = useState([])

  useEffect(() => {
    if (taskValues.after) {
      setSecondSelectOptions(generateTimeSlots(moment(taskValues.after)))
    }
  }, [taskValues.after])

  const handleDateChange = newValue => {
    const afterHour = moment(taskValues.after).format('HH:mm:ss')
    const beforeHour = moment(taskValues.before).format('HH:mm:ss')
    const newDate = newValue.format('YYYY-MM-DD')

    setFieldValue(
      `tasks[${taskIndex}].after`,
      moment(`${newDate} ${afterHour}`).toISOString(true),
    )
    setFieldValue(
      `tasks[${taskIndex}].before`,
      moment(`${newDate} ${beforeHour}`).toISOString(true),
    )
  }

  const handleAfterHourChange = newValue => {
    const date = moment(taskValues.after).format('YYYY-MM-DD')
    const newAfter = moment(`${date} ${newValue}:00`)
    const newBefore = newAfter.clone().add(10, 'minutes')

    setFieldValue(`tasks[${taskIndex}].after`, newAfter.toISOString(true))
    setFieldValue(`tasks[${taskIndex}].before`, newBefore.toISOString(true))
  }

  const handleBeforeHourChange = newValue => {
    const date = moment(taskValues.after).format('YYYY-MM-DD')
    const newBefore = moment(`${date} ${newValue}:00`)
    setFieldValue(`tasks[${taskIndex}].before`, newBefore.toISOString(true))
  }

  return (
    <div className="picker-container">
      {mode !== Mode.RECURRENCE_RULE_UPDATE ? (
        <DatePicker
          data-testid="date-picker"
          className="picker-container__datepicker mr-2"
          format={format}
          // defaultValue={afterValue || defaultAfterValue}
          value={moment(taskValues.after)}
          onChange={newDate => {
            handleDateChange(newDate)
          }}
        />
      ) : null}

      <Select
        data-testid={`select-after`}
        className="picker-container__select-left mr-2"
        format={format}
        value={moment(taskValues.after).format('HH:mm')}
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
        data-testid={`select-before`}
        className="picker-container__select-right"
        format={format}
        value={moment(taskValues.before).format('HH:mm')}
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
  )
}

export default SameDayPicker
