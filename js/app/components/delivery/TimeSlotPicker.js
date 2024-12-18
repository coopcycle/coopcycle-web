import React, { useState, useEffect } from 'react'
import { DatePicker, Select, Radio } from 'antd'
import moment from 'moment'

import 'antd/es/input/style/index.css'

export default ({ choices, initialChoices, defaultTimeSlotName }) => {
  const timeSlotsLabel = []
  for (const timeSlot in choices) {
    timeSlotsLabel.push(timeSlot)
  }

  console.log('initialChoices', initialChoices)

  // choices : la liste des options avec leur timeslot
  // initialChoices : on initialise avec l'option par défaut et ses timeSlot

  const [timeSlotChoices, setTimeSlotChoices] = useState(null)

  useEffect(() => setTimeSlotChoices(initialChoices), [initialChoices])

  console.log('timeSlotChoices', timeSlotChoices)

  const datesWithTimeslots = {}

  /**
   * on vient looper sur timeSlotChoices
   * on sépare les deux valeurs first/second
   * puis on sépare encore les dates des heures
   * on vient prendre les deux heures pour créer une string avec heure1-heure2 (enlever le Z de fin et mettre que les 5 premiers caractères)
   * et on reprend la logique : si la clé existe; on push notre time slot dans l'array
   * sinon on crée cet array avec les heures
   * Le format de chaque item de la liste :  "2024-12-23T02:00:00Z/2024-12-23T03:00:00Z"
   */

  useEffect(() => {
    const formatTimeSlots = () => {
      if (!timeSlotChoices === null) {
        timeSlotChoices.forEach(choice => {
          let [first, second] = choice.value.split('/')
          first = moment(first)
          second = moment(second)
          test.push(first, second)
          console.log('heures', first, second)
        })
      }
    }
    formatTimeSlots()
  }, [timeSlotChoices])

  // timeSlotChoices.forEach(choice => {
  //   const [first, second] = choice.value.split('/')
  //   const
  //   if (Object.prototype.hasOwnProperty.call(datesWithTimeslots, date)) {
  //     datesWithTimeslots[date].push(hour)
  //   } else {
  //     datesWithTimeslots[date] = [hour]
  //   }
  // })

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

  // const handleInitialChoicesChange = timeSlot => {
  //   setTimeSlotChoices(choices[timeSlot.target.value])
  // }

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
          defaultValue={defaultTimeSlotName}
          buttonStyle="solid"
          style={{ display: 'flex' }}>
          {timeSlotsLabel.map(label => (
            <Radio.Button
              key={label}
              value={label}
              // onChange={timeSlot => {
              //   handleInitialChoicesChange(timeSlot)
              // }}
            >
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
