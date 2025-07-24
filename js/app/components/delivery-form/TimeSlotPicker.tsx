import React, { useState, useEffect, useCallback } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment, { Moment } from 'moment'
import { useTranslation } from 'react-i18next'

import './TimeSlotPicker.scss'
import Spinner from '../core/Spinner'
import {
  useDeliveryFormFormikContext
} from './hooks/useDeliveryFormFormikContext'
import { useGetStoreQuery, useGetTimeSlotChoicesQuery } from '../../api/slice'
import { Mode } from './mode'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'
import type { TimeSlot } from './types'

const extractDateAndRangeFromTimeSlot = (timeSlotChoice) => {
  let [first, second] = timeSlotChoice.split('/')
  first = moment(first)
  second = moment(second)
  const date = moment(first)
  const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
  return { date, hour }
}

const formatSlots = (timeSlotChoices) => {
  const formattedSlots = {}
  timeSlotChoices.forEach(choice => {
    const { date, hour } = extractDateAndRangeFromTimeSlot(choice.value)
    const formattedDate = date.format('YYYY-MM-DD')
    if (formattedSlots[formattedDate]) {
      formattedSlots[formattedDate].push(hour)
    } else {
      formattedSlots[formattedDate] = [hour]
    }
  })

  return formattedSlots
}

const InputLabel = () => {
  const { t } = useTranslation()

  return (<div className="mb-2 font-weight-bold title-slot">{t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE')}</div>)
}

type Props = {
  storeNodeId: string
  taskId: string
  timeSlotLabels: TimeSlot[]
}

const TimeSlotPicker = ({ storeNodeId, taskId, timeSlotLabels }: Props) => {

  const { data: store } = useGetStoreQuery(storeNodeId)

  const { t } = useTranslation()

  const mode = useSelector(selectMode)
  const { taskValues, taskIndex: index, setFieldValue } = useDeliveryFormFormikContext({
    taskId: taskId,
  })

  const { data: timeSlotChoicesResponse, isFetching: isLoadingChoices } = useGetTimeSlotChoicesQuery(taskValues.timeSlotUrl, {
    skip: !taskValues.timeSlotUrl
  })

  const storeTimeSlotIds = store?.timeSlots
  const storeDefaultTimeSlotId = store?.timeSlot

  const [formattedTimeslots, setFormattedTimeslots] = useState<Record<string, any>>({})

  const setTimeSlotUrl = useCallback((timeSlotUrl: string) => {
    setFieldValue(`tasks[${index}].timeSlotUrl`, timeSlotUrl)
  }, [index, setFieldValue])

  const setTimeSlot = useCallback((timeSlot) => {
    setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
  }, [index, setFieldValue])


  const setTimeSlotFromDateAndRange = useCallback((date, hourRange) => {
    const [first, second] = hourRange.split('-')
    const startTime = date.clone().hours(first.split(':')[0]).minutes(first.split(':')[1])
    const endTime = date.clone().hours(second.split(':')[0]).minutes(second.split(':')[1])
    const timeSlot = `${startTime.utc().format('YYYY-MM-DDTHH:mm:00')}Z/${endTime.utc().format('YYYY-MM-DDTHH:mm:00')}Z`
    setTimeSlot(timeSlot)
  }, [setTimeSlot])

  // Preselect a time slot if no time slot is selected
  useEffect(() => {
    if (mode === Mode.DELIVERY_UPDATE) {
      return
    }

    if (taskValues.timeSlotUrl) {
      return
    }

    if (storeTimeSlotIds.length === 0) {
      return
    }

    setTimeSlotUrl(storeDefaultTimeSlotId ?? storeTimeSlotIds[0])

  }, [storeTimeSlotIds, storeDefaultTimeSlotId, taskValues.timeSlotUrl, setTimeSlotUrl, mode])

  // Load time slot choices when timeSlotUrl changes
  useEffect(() => {
    if (!timeSlotChoicesResponse) {
      return
    }

    if (timeSlotChoicesResponse['choices'].length === 0) {
      // Remove a time slot if no choices are available

      setTimeSlotUrl(null)
      setTimeSlot(null)

    } else {
      // Preselect the first available time slot choice

      const formattedSlots = formatSlots(timeSlotChoicesResponse['choices'])
      setFormattedTimeslots(formattedSlots)

      const availableDates = Object.keys(formattedSlots)
      const firstDate = moment(availableDates[0])
      setTimeSlotFromDateAndRange(firstDate, formattedSlots[availableDates[0]][0])
    }
  }, [timeSlotChoicesResponse, setTimeSlotFromDateAndRange, setTimeSlotUrl, setTimeSlot])

  const handleTimeSlotLabelChange = e => {
    setTimeSlotUrl(e.target.value)
    setTimeSlot(null) // Will be set after the choices are loaded
  }

  const handleDateChange = newDate => {
    const { hour } = extractDateAndRangeFromTimeSlot(taskValues.timeSlot)
    setTimeSlotFromDateAndRange(newDate, hour)
  }

  const handleHourChange = hourRange => {
    const { date } = extractDateAndRangeFromTimeSlot(taskValues.timeSlot)
    setTimeSlotFromDateAndRange(date, hourRange)
  }

  if (isLoadingChoices || !taskValues.timeSlot) {
    return (
      <>
        <InputLabel />
        <Spinner />
      </>)
  }

  const availableDates = Object.keys(formattedTimeslots || {}).map(date => moment(date))

  function isDateDisabled(current) {
    return !availableDates.some(date => date.isSame(current, 'day'))
  }

  const selectedDate = extractDateAndRangeFromTimeSlot(taskValues.timeSlot).date
  const selectedHour = extractDateAndRangeFromTimeSlot(taskValues.timeSlot).hour

  const hourOptions = formattedTimeslots[selectedDate.format('YYYY-MM-DD')] || []

  return (
    <>
      <InputLabel />

      {(timeSlotLabels && timeSlotLabels.length > 1) ?
        <Radio.Group
          className="timeslot__container mb-2"
          value={taskValues.timeSlotUrl}
        >
          {timeSlotLabels.map(label => (
            <Radio.Button
              key={label.name}
              value={label['@id']}
              onChange={timeSlotUrl => {
                handleTimeSlotLabelChange(timeSlotUrl)
              }}>
              {label.name}
            </Radio.Button>
          ))}
        </Radio.Group>
        : null
      }

      { hourOptions.length > 0 ?
        <div style={{ display: 'flex', marginTop: '0.5em' }}>
          <DatePicker
            data-testid="date-picker"
            format="LL"
            style={{ width: '60%' }}
            className="mr-2"
            disabledDate={isDateDisabled}
            disabled={availableDates.length > 1 ? false : true}
            value={selectedDate}
            onChange={date => {
              handleDateChange(date)
            }}
          />
          <Select
            data-testid="hour-picker"
            style={{ width: '35%' }}
            onChange={option => {
              handleHourChange(option)
            }}
            value={selectedHour}
          >
            {
              hourOptions.map(option => (
                <Select.Option key={option} value={option}>
                  {option}
                </Select.Option>
              ))
            }
          </Select>
        </div>
        : <p>{t('ADMIN_DASHBOARD_NO_TIMESLOTS_AVAILABLE')}</p>
      }
    </>
  )
}

export default TimeSlotPicker
