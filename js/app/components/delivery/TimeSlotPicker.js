import React, { useState, useEffect, useCallback } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'
import axios from 'axios'

import 'antd/es/input/style/index.css'

const baseURL = location.protocol + '//' + location.host

export default ({ choices, initialChoices, defaultTimeSlotName, storeId }) => {
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [storeDeliveryLabels, setStoreDeliveryLabels] = useState([])

  console.log(storeDeliveryLabels)

  useEffect(() => {
    const fetchStoreInfos = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt

      const url = `${baseURL}/api/stores/${storeId}`

      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      setStoreDeliveryInfos(response.data)
    }
    if (storeId) {
      fetchStoreInfos()
    }
  }, [storeId])

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

  /** We format the data in order for them to fit in a datepicker and a select */

  const [datesWithTimeslots, setDatesWithTimeslots] = useState({})

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
    }
    formatTimeSlots()
  }, [timeSlotChoices])

  console.log('dateswithts', datesWithTimeslots)

  // const dates = []

  // for (const date in datesWithTimeslots) {
  //   dates.push(moment(date))
  // }

  // function disabledDate(current) {
  //   return !dates.some(date => date.isSame(current, 'day'))
  // }

  // const [values, setValues] = useState({})

  // const [options, setOptions] = useState([])

  // useEffect(() => {
  //   setOptions(datesWithTimeslots[dates[0].format('YYYY-MM-DD')])
  //   setValues({
  //     date: dates[0],
  //     option: datesWithTimeslots[dates[0].format('YYYY-MM-DD')][0],
  //   })
  // }, [timeSlotChoices])

  const handleInitialChoicesChange = e => {
    const label = storeDeliveryLabels.find(
      label => label.name === e.target.value,
    )
    const timeSlotUrl = label['@id']
    getTimeSlotOptions(timeSlotUrl)
  }

  // const handleDateChange = newDate => {
  //   setValues({
  //     date: newDate,
  //     option: datesWithTimeslots[newDate.format('YYYY-MM-DD')][0],
  //   })
  //   setOptions(datesWithTimeslots[newDate.format('YYYY-MM-DD')])
  // }

  // const handleTimeSlotChange = newTimeslot => {
  //   setValues(prevState => ({ ...prevState, option: newTimeslot }))
  // }

  return (
    <>
      {Object.keys(choices).length > 1 ? (
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
        <DatePicker
          format="LL"
          style={{ width: '60%' }}
          className="mr-2"
          // disabledDate={disabledDate}
          // disabled={dates.length > 1 ? false : true}
          // value={values.date}
          // onChange={date => {
          //   handleDateChange(date)
          // }}
        />

        {/* <Select
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
        </Select> */}
      </div>
    </>
  )
}
