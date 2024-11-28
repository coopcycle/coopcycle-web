import  React, {useState } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd';

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const {Option} = Select


const DateTimeRangePicker = ({ defaultValue, onChange, format}) => {
  
  const [value, setValue] = useState(() => defaultValue ? [moment(defaultValue.after), moment(defaultValue.before)] : [])

  function calculate(endTime, minutes) {
    const timeStops = [];
    const startTime = moment().add('m', (minutes - moment().minute() % 15) + 15);

    while (startTime < endTime) {
      timeStops.push(new moment(startTime).format('HH:mm'));
      startTime.add('m', 15);
    }

    return timeStops;
}

  const firstSelectOptions = calculate(moment(value[0]).add('h', 24), 15);
  const secondSelectOptions = calculate(moment(value[1]).add('h', 24), 30);


  
  const handleChange = ({type, newValue}) => {

    if (!newValue) return // est ce que c'est pas redondant avec le return du switch ? 

    let afterValue = value[0]
    let beforeValue = value[1]

    switch (type) {
      case "date":
        const afterHour = afterValue.format("HH:mm:ss")
        const beforeHour = beforeValue.format("HH:mm:ss")

        const newDate = newValue.format("YYYY-MM-DD")
        
        afterValue = moment(`${newDate} ${afterHour}`)
        beforeValue = moment(`${newDate} ${beforeHour}`)
        setValue([afterValue, beforeValue])
        break
      case "afterHour": 
        const date = afterValue.format("YYYY-MM-DD")    
        afterValue = moment(`${date} ${newValue}:00`)
        beforeValue = beforeValue
        setValue([afterValue, beforeValue])
        break
      case "beforeHour":   
        // pourquoi je peux pas redéclarer date alors que c'est pourtant hors portée ?
        const oldDate = afterValue.format("YYYY-MM-DD")
        beforeValue = moment(`${oldDate} ${newValue}:00`)
        afterValue = afterValue
        setValue([afterValue, beforeValue])
        break
      default:
        return
    }

    // on verifie que newValueBefore > newValueAfter. Si non : message d'erreur ? Ou valeur dans le select ou classe d'erreur ? Mais comment
    // ne pas ajouter un state ?

    const isBefore = moment(afterValue).isBefore(beforeValue)

    if (!isBefore) {
      console.log("la première heure est après la seconde")
      console.log(isBefore)
    } else {
      onChange({
        after: afterValue,
        before: beforeValue
      })
    }
  }

  return (

    <>
    <DatePicker
      style={{ width: '50%' }}
      format="LL"
      defaultValue={value[0]} 
      onChange={(newDate) => {
        handleChange({ type: "date", newValue: newDate })
      }}
      
    />
    <Select
      style={{ width: '25%' }}
      format={format}
      defaultValue={value[0].format('HH:mm')} 
      onChange={(newAfterHour) => {
        handleChange({ type: "afterHour", newValue: newAfterHour })
      }}
      >
     {firstSelectOptions.map((option) => (
        <Option key={option} value={option}>
          {option}
        </Option>
      ))}

    </Select>
    <Select
      style={{ width: '25%' }}
      format={format}
      defaultValue={value[1].format('HH:mm')} 
      onChange={(newBeforeHour) => {
        handleChange({ type: "beforeHour", newValue: newBeforeHour })
      }}
      >
        {secondSelectOptions.map((option) => (
        <Option key={option} value={option}>
          {option}
        </Option>
      ))}
      </Select>
      </>

    )
  }

export default function(el, options) {

  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {},
    format: 'LLL', // verifier ce qu'on met là 
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={ antdLocale }>
      <DateTimeRangePicker { ...props } />
    </ConfigProvider>, el)
}
