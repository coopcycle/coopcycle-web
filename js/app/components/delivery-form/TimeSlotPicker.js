import React, { useState, useEffect } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './TimeSlotPicker.scss'
import Spinner from '../core/Spinner'
import { usePrevious } from '../../dashboard/redux/utils'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, storeDeliveryInfos, index }) => {
  const httpClient = new window._auth.httpClient()

  const { t } = useTranslation()

  const { setFieldValue, values } = useFormikContext()

  const previousTimeSlotName = usePrevious(values.tasks[index].timeSlotName)

  const [timeSlotLabels, setStoreLabels] = useState(null)
  const [formattedTimeslots, setFormattedTimeslots] = useState(null)
  const [isLoadingChoices, setIsLoadingChoices] = useState(false)
  const [selectedValues, setSelectedValues] = useState({})

  const extractDateAndRangeFromTimeSlot = (timeSlotChoice) => {
    let [first, second] = timeSlotChoice.split('/')
    first = moment(first)
    second = moment(second)
    const date = moment(first).format('YYYY-MM-DD')
    const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
    return {date, hour}
  }

  const extractTimeSlotsDateAndHour = (timeSlotChoices) => {
    const formattedSlots = {}
    timeSlotChoices.forEach(choice => {
      const {date, hour} = extractDateAndRangeFromTimeSlot(choice.value)
      if (formattedSlots[date]) {
        formattedSlots[date].push(hour)
      } else {
        formattedSlots[date] = [hour]
      }
    })

    return formattedSlots
  }

  const getTimeSlotsLabels = async () => {
    const url = `${baseURL}/api/stores/${storeId}/time_slots`

    const { response } = await httpClient.get(url)
    const timeSlotLabels = response['hydra:member']
    setStoreLabels(timeSlotLabels)
    
    const defaultLabel = timeSlotLabels.find(label => label['@id'] === storeDeliveryInfos.timeSlot)
    setFieldValue(`tasks[${index}].timeSlotName`, defaultLabel.name)
  }

  const getTimeSlotChoices = async timeSlotUrl => {

    setIsLoadingChoices(true)

    const url = `${baseURL}${timeSlotUrl}/choices`
    const { response } = await httpClient.get(url)
    const formattedSlots = extractTimeSlotsDateAndHour(response['choices'])

    setFormattedTimeslots(formattedSlots)

    const availableDates = Object.keys(formattedSlots)
    if (availableDates.length > 0) {
      const firstDate = moment(availableDates[0])
      setSelectedValues({
        date: firstDate,
        hour: formattedSlots[availableDates[0]][0],
      })
    }

    setIsLoadingChoices(false)
  }

  useEffect(() => {
    // on load, get all the timeslotslabel
    getTimeSlotsLabels()

    // load the first timeslot choices
    const timeSlotUrl = storeDeliveryInfos.timeSlot
    getTimeSlotChoices(timeSlotUrl)

  }, [storeDeliveryInfos])

  useEffect(() => {
    if (previousTimeSlotName && previousTimeSlotName !== values.tasks[index].timeSlotName) {
      const timeSlotUrl = timeSlotLabels.find(label => label.name === values.tasks[index].timeSlotName)['@id']
      getTimeSlotChoices(timeSlotUrl)
    }
  }, [values.tasks[index].timeSlotName])

  useEffect(() => {
    if (Object.keys(selectedValues).length !== 0) {
      const date = selectedValues.date.format('YYYY-MM-DD')
      const range = selectedValues.hour
      const [first, second] = range.split('-')
      const timeSlot = `${date}T${first}:00Z/${date}T${second}:00Z`
      setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
    }
  }, [selectedValues])

  const handleTimeSlotLabelChange = e => {
    const label = timeSlotLabels.find(
      label => label.name === e.target.value,
    )
    const timeSlotUrl = label['@id']
    getTimeSlotChoices(timeSlotUrl)
    setFieldValue(`tasks[${index}].timeSlotName`, label.name)
  }

  const handleDateChange = newDate => {
    if (!newDate) return

    setSelectedValues({
      date: newDate,
      hour: formattedTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
  }

  const handleHourChange = hour => {
    setSelectedValues(prevState => ({ ...prevState, hour: hour }))
  }

  const inputLabel = () => <div className="mb-2 font-weight-bold title-slot">{t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE')}</div>

  if (!timeSlotLabels || isLoadingChoices || !values.tasks[index].timeSlot) {
    return (
      <>
        {inputLabel() }
        <Spinner />
      </>)
  }

  const availableDates = Object.keys(formattedTimeslots || {}).map(date => moment(date))

  function isDateDisabled(current) {
    return !availableDates.some(date => date.isSame(current, 'day'))
  }

  const selectedDate = moment(extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).date)
  const selectedHour = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).hour
  
  const hourOptions = formattedTimeslots[selectedDate.format('YYYY-MM-DD')]

  return (
    <>
      { inputLabel() }
      <Radio.Group
        className="timeslot__container mb-2"
        value={values.tasks[index].timeSlotName}
      >
        {timeSlotLabels.map(label => (
          <Radio.Button
            key={label.name}
            value={label.name}
            onChange={timeSlotName => {
              handleTimeSlotLabelChange(timeSlotName)
            }}>
            {label.name}
          </Radio.Button>
        ))}
      </Radio.Group>

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
    </>
  )
}
