import React, { useState, useEffect, useCallback } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import axios from 'axios'

import 'antd/es/input/style/index.css'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, storeDeliveryInfos }) => {
  const [storeDeliveryLabels, setStoreDeliveryLabels] = useState([])

  console.log(storeDeliveryLabels)

  useEffect(() => {
    const getTimeSlotsLabels = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/stores/${storeId}/time_slots`

      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      const timeSlotsLabel = await response.data['hydra:member']
      setStoreDeliveryLabels(timeSlotsLabel)
    }
    if (storeId) {
      getTimeSlotsLabels()
    }
  }, [storeId])

  /** We get the labels available and the default label for the radio buttons */

  const getLabels = useCallback(() => {
    const timeSlotsLabels = []
    for (const label of storeDeliveryLabels) {
      timeSlotsLabels.push(label.name)
    }

    return timeSlotsLabels
  }, [storeDeliveryLabels])

  const timeSlotsLabels = getLabels()

  const getDefaultLabels = useCallback(() => {
    return storeDeliveryLabels.find(
      label => label['@id'] === storeDeliveryInfos.timeSlot,
    )
  }, [storeDeliveryLabels, storeDeliveryInfos])

  const defaultLabel = getDefaultLabels()

  /** We initialize with the default timesSlots, then changed when user selects a different option */

  const [timeSlotChoices, setTimeSlotChoices] = useState([])

  const getTimeSlotOptions = async timeSlotUrl => {
    const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
    const jwt = jwtResp.jwt
    const url = `${baseURL}${timeSlotUrl}/choices`
    const response = await axios.get(url, {
      headers: {
        Authorization: `Bearer ${jwt}`,
      },
    })
    const choices = response.data['choices']
    setTimeSlotChoices(choices)
  }

  useEffect(() => {
    const timeSlotUrl = storeDeliveryInfos.timeSlot
    getTimeSlotOptions(timeSlotUrl)
  }, [storeDeliveryInfos])

  /** We format the data in order for them to fit in a datepicker and a select
   * We initialize the datepicker's and the select's values
   */

  const [datesWithTimeslots, setDatesWithTimeslots] = useState({})
  const [values, setValues] = useState({})
  const [options, setOptions] = useState([])

  useEffect(() => {
    const formatTimeSlots = () => {
      const formattedSlots = {}
      timeSlotChoices.forEach(choice => {
        let [first, second] = choice.value.split('/')
        first = moment(first)
        second = moment(second)
        const date = moment(first).format('YYYY-MM-DD')
        const hour = `${first.format('HH:mm')}-${second.format('HH:mm')}`
        if (formattedSlots[date]) {
          formattedSlots[date].push(hour)
        } else {
          formattedSlots[date] = [hour]
        }
      })
      setDatesWithTimeslots(formattedSlots)

      const availableDates = Object.keys(formattedSlots)
      if (availableDates.length > 0) {
        const firstDate = moment(availableDates[0])
        setOptions(formattedSlots[availableDates[0]])
        setValues({
          date: firstDate,
          option: formattedSlots[availableDates[0]][0],
        })
      }
    }
    formatTimeSlots()
  }, [timeSlotChoices])

  console.log('dateswithts', datesWithTimeslots)

  /** disabled dates */

  const dates = Object.keys(datesWithTimeslots || {}).map(date => moment(date))

  function disabledDate(current) {
    return !dates.some(date => date.isSame(current, 'day'))
  }

  console.log(values, options)
  const handleInitialChoicesChange = e => {
    const label = storeDeliveryLabels.find(
      label => label.name === e.target.value,
    )
    const timeSlotUrl = label['@id']
    getTimeSlotOptions(timeSlotUrl)
  }

  const handleDateChange = newDate => {
    setValues({
      date: newDate,
      option: datesWithTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
    setOptions(datesWithTimeslots[newDate.format('YYYY-MM-DD')])
  }

  const handleTimeSlotChange = newTimeslot => {
    setValues(prevState => ({ ...prevState, option: newTimeslot }))
  }

  return (
    <>
      {defaultLabel && timeSlotsLabels ? (
        <Radio.Group
          defaultValue={defaultLabel.name}
          buttonStyle="solid"
          style={{ display: 'flex' }}>
          {timeSlotsLabels.map(label => (
            <Radio.Button
              key={label}
              value={label}
              onChange={timeSlot => {
                handleInitialChoicesChange(timeSlot)
              }}>
              {label}
            </Radio.Button>
          ))}
        </Radio.Group>
      ) : null}

      <div style={{ display: 'flex', marginTop: '0.5em' }}>
        {values.date ? (
          <DatePicker
            format="LL"
            style={{ width: '60%' }}
            className="mr-2"
            disabledDate={disabledDate}
            disabled={dates.length > 1 ? false : true}
            value={values.date}
            onChange={date => {
              handleDateChange(date)
            }}
          />
        ) : null}

        {values.option && options ? (
          <Select
            style={{ width: '35%' }}
            onChange={option => {
              handleTimeSlotChange(option)
            }}
            value={values.option}>
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
