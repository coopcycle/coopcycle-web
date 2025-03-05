import React, { useState, useEffect } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './TimeSlotPicker.scss'
import Spinner from '../core/Spinner'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, storeDeliveryInfos, index }) => {
  const httpClient = new window._auth.httpClient()

  const { t } = useTranslation()

  const { setFieldValue, values } = useFormikContext()

  const [storeDeliveryLabels, setStoreDeliveryLabels] = useState(null)

  const getTimeSlotsLabels = async () => {
    const url = `${baseURL}/api/stores/${storeId}/time_slots`

    const { response } = await httpClient.get(url)

    if (response) {
      const timeSlotsLabel = response['hydra:member']
      setStoreDeliveryLabels(timeSlotsLabel)
    }
  }

  useEffect(() => {
    // on load, get all the timeslotslabel
    getTimeSlotsLabels()

    // load the first timeslot choices
    const timeSlotUrl = storeDeliveryInfos.timeSlot
    getTimeSlotOptions(timeSlotUrl)

  }, [storeDeliveryInfos])

  /** We initialize with the default timesSlots, then changed when user selects a different option */

  const [timeSlotChoices, setTimeSlotChoices] = useState(null)

  const getTimeSlotOptions = async timeSlotUrl => {
    const url = `${baseURL}${timeSlotUrl}/choices`
    const { response } = await httpClient.get(url)
    if (response) {
      setTimeSlotChoices(response['choices'])
    }
  }

  /** We format the data in order for them to fit in a datepicker and a select
   * We initialize the datepicker's and the select's values
   */

  const [formattedTimeslots, setFormattedTimeslots] = useState({})
  const [selectedValues, setSelectedValues] = useState({})
  const [options, setOptions] = useState([])

  const extractDateAndRangeFromTimeSlot = (timeSlotChoice) => {
    let [first, second] = timeSlotChoice.split('/')
    first = moment(first)
    second = moment(second)
    const date = moment(first).format('YYYY-MM-DD')
    const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
    return {date, hour}
  }

  const extractTimeSlotsDateAndHour = () => {
    const formattedSlots = {}
    timeSlotChoices.forEach(choice => {
      const {date, hour} = extractDateAndRangeFromTimeSlot(choice.value)
      if (formattedSlots[date]) {
        formattedSlots[date].push(hour)
      } else {
        formattedSlots[date] = [hour]
      }
    })

    setFormattedTimeslots(formattedSlots)

    const availableDates = Object.keys(formattedSlots)
    if (availableDates.length > 0) {
      const firstDate = moment(availableDates[0])
      setOptions(formattedSlots[availableDates[0]])
      setSelectedValues({
        date: firstDate,
        option: formattedSlots[availableDates[0]][0],
      })
    }
  }

  useEffect(() => {
    if (timeSlotChoices) {
      extractTimeSlotsDateAndHour()
    }
  }, [timeSlotChoices])

  useEffect(() => {
    if (Object.keys(selectedValues).length !== 0) {
      const date = selectedValues.date.format('YYYY-MM-DD')
      const range = selectedValues.option
      const [first, second] = range.split('-')
      const timeSlot = `${date}T${first}:00Z/${date}T${second}:00Z`
      setFieldValue(`tasks[${index}].timeSlot`, timeSlot)
    }
  }, [selectedValues])

  const handleTimeSlotLabelChange = e => {
    const label = storeDeliveryLabels.find(
      label => label.name === e.target.value,
    )
    const timeSlotUrl = label['@id']
    setFieldValue(`tasks[${index}].timeSlotName`, label.name)
    getTimeSlotOptions(timeSlotUrl)
  }

  const handleDateChange = newDate => {
    if (!newDate) return

    setSelectedValues({
      date: newDate,
      option: formattedTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
    setOptions(formattedTimeslots[newDate.format('YYYY-MM-DD')])
  }

  const handleTimeSlotChange = newTimeslot => {
    if (!newTimeslot) return
    setSelectedValues(prevState => ({ ...prevState, option: newTimeslot }))
  }

  if (!storeDeliveryLabels || !timeSlotChoices || !values.tasks[index].timeSlot) {
    return <Spinner />
  }

  const availableDates = Object.keys(formattedTimeslots || {}).map(date => moment(date))

  function isDateDisabled(current) {
    return !availableDates.some(date => date.isSame(current, 'day'))
  }

  const defaultLabel = storeDeliveryLabels.find(label => label['@id'] === storeDeliveryInfos.timeSlot)

  const selectedDate = moment(extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).date)
  const selectedHour = extractDateAndRangeFromTimeSlot(values.tasks[index].timeSlot).hour

  return (
    <>
      <div className="mb-2 font-weight-bold title-slot">
        {t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE')}
      </div>
      <Radio.Group
        className="timeslot__container mb-2"
        defaultValue={defaultLabel.name}
        value={values.tasks[index].timeSlotName || defaultLabel.name}
      >
        {storeDeliveryLabels.map(label => (
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
        {selectedValues.date ? (
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
        ) : null}

        {selectedValues.option && options ? (
          <Select
            style={{ width: '35%' }}
            onChange={option => {
              handleTimeSlotChange(option)
            }}
            value={selectedHour}
          >
            {options.length >= 1 &&
              options.map(option => (
                <Select.Option key={option} value={option}>
                  {option}
                </Select.Option>
              ))}
          </Select>
        ) : null}
      </div>
    </>
  )
}
