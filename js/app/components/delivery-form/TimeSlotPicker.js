import React, { useState, useEffect } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './TimeSlotPicker.scss'
import Spinner from '../core/Spinner'

const baseURL = location.protocol + '//' + location.host

export default ({ index, timeSlotLabels }) => {

  const httpClient = new window._auth.httpClient()

  const { t } = useTranslation()

  const { setFieldValue, values } = useFormikContext()

  const [formattedTimeslots, setFormattedTimeslots] = useState(null)
  const [isLoadingChoices, setIsLoadingChoices] = useState(false)

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

  const setTimeSlotFromDateAndRange = (date, hourRange) => {
    const [first, second] = hourRange.split('-')
    const startTime = date.clone().hours(first.split(':')[0]).minutes(first.split(':')[1])
    const endTime = date.clone().hours(second.split(':')[0]).minutes(second.split(':')[1])
    const timeSlot = `${startTime.utc().format('YYYY-MM-DDTHH:mm:00')}Z/${endTime.utc().format('YYYY-MM-DDTHH:mm:00')}Z`
    setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
  }

  const getTimeSlotChoices = async timeSlotUrl => {
    setIsLoadingChoices(true)

    const url = `${baseURL}${timeSlotUrl}/choices`
    const { response } = await httpClient.get(url)

    if (response['choices'].length === 0) {
      setFieldValue(`tasks[${index}].timeSlot`, 'No choice')
    } else {
      const formattedSlots = formatSlots(response['choices'])
      setFormattedTimeslots(formattedSlots)

      const availableDates = Object.keys(formattedSlots)
      const firstDate = moment(availableDates[0])
      setTimeSlotFromDateAndRange(firstDate, formattedSlots[availableDates[0]][0])
    }

    setIsLoadingChoices(false)
  }

  useEffect(() => {
    getTimeSlotChoices(values.tasks[index].timeSlotUrl)
  }, [values.tasks[index].timeSlotUrl])

  const handleTimeSlotLabelChange = e => {
    setFieldValue(`tasks[${index}].timeSlot`, null)
    setFieldValue(`tasks[${index}].timeSlotUrl`, e.target.value)
  }

  const handleDateChange = newDate => {
    const { hour } = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot)
    setTimeSlotFromDateAndRange(newDate, hour)
  }

  const handleHourChange = hourRange => {
    const { date } = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot)
    setTimeSlotFromDateAndRange(date, hourRange)
  }

  const inputLabel = () => <div className="mb-2 font-weight-bold title-slot">{t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE')}</div>

  if (isLoadingChoices || !values.tasks[index].timeSlot) {
    return (
      <>
        {inputLabel()}
        <Spinner />
      </>)
  }

  const availableDates = Object.keys(formattedTimeslots || {}).map(date => moment(date))

  function isDateDisabled(current) {
    return !availableDates.some(date => date.isSame(current, 'day'))
  }

  const selectedDate = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).date
  const selectedHour = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).hour

  const hourOptions = formattedTimeslots[selectedDate.format('YYYY-MM-DD')] || []

  return (
    <>
      {inputLabel()}

      {timeSlotLabels.length > 1 ?
        <Radio.Group
          className="timeslot__container mb-2"
          value={values.tasks[index].timeSlotUrl}
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
