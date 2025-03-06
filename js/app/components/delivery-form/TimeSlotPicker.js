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
  const [selectedValues, setSelectedValues] = useState({})

  const extractDateAndRangeFromTimeSlot = (timeSlotChoice) => {
    let [first, second] = timeSlotChoice.split('/')
    first = moment(first)
    second = moment(second)
    const date = moment(first).format('YYYY-MM-DD')
    const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
    return { date, hour }
  }

  const extractTimeSlotsDateAndHour = (timeSlotChoices) => {
    const formattedSlots = {}
    timeSlotChoices.forEach(choice => {
      const { date, hour } = extractDateAndRangeFromTimeSlot(choice.value)
      if (formattedSlots[date]) {
        formattedSlots[date].push(hour)
      } else {
        formattedSlots[date] = [hour]
      }
    })

    return formattedSlots
  }

  const getTimeSlotChoices = async timeSlotUrl => {
    setIsLoadingChoices(true)

    const url = `${baseURL}${timeSlotUrl}/choices`
    const { response } = await httpClient.get(url)

    if (response['choices'].length === 0) {
      console.log('no choices')
      setFieldValue(`tasks[${index}].timeSlot`, 'No choice')
    } else {
      const formattedSlots = extractTimeSlotsDateAndHour(response['choices'])
      setFormattedTimeslots(formattedSlots)
      const availableDates = Object.keys(formattedSlots)
      const firstDate = moment(availableDates[0])
      setSelectedValues({
        date: firstDate,
        hour: formattedSlots[availableDates[0]][0],
      })  
    }

    setIsLoadingChoices(false)
  }

  useEffect(() => {
    getTimeSlotChoices(values.tasks[index].timeSlotUrl)
  }, [values.tasks[index].timeSlotUrl])

  useEffect(() => {
    console.log('slected changed', selectedValues)
    if (Object.keys(selectedValues).length !== 0) {
      const date = selectedValues.date.format('YYYY-MM-DD')
      const range = selectedValues.hour
      const [first, second] = range.split('-')
      const timeSlot = `${date}T${first}:00Z/${date}T${second}:00Z`
      setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
    }
  }, [selectedValues])

  const handleTimeSlotLabelChange = e => {
    setFieldValue(`tasks[${index}].timeSlot`, null)
    setFieldValue(`tasks[${index}].timeSlotUrl`, e.target.value)
  }

  const handleDateChange = newDate => {
    setSelectedValues({
      date: newDate,
      hour: formattedTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
  }

  const handleHourChange = hour => {
    console.log(hour)
    setSelectedValues(prevState => ({ ...prevState, hour: hour }))
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

  const selectedDate = moment(extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).date)
  const selectedHour = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).hour

  console.log(values.tasks[index].timeSlot)
  console.log(selectedHour)

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
