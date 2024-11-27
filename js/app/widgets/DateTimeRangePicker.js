import  React, {useState } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd';

import { timePickerProps } from '../utils/antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const {Option} = Select

// pas de show Time ici parce qu'on veut juste la date 

// il faut que le onChange soit au courant des changements 
const DateTimeRangePicker = ({ defaultValue, onChange, format}) => {
  
  const [value, setValue] = useState(() => defaultValue ? [moment(defaultValue.after), moment(defaultValue.before)] : [])

  const [date, setDate] = useState(() => defaultValue ? moment(defaultValue.after) : null)
  const [doneAfterHour, setDoneAfterHour] = useState(() => defaultValue ? moment(defaultValue.after).format("HH:mm:ss") : "")
  const [doneBeforeHour, setDoneBeforeHour] = useState(() => defaultValue ? moment(defaultValue.before).format("HH:mm:ss") : "")

  // Initialiser les states avec les options à partir des doneAfterHour et doneBeforeHour 

    // la fonction pour générer des intervales à partir de l'heure actuelle 

  function calculate(endTime, minutes) {
    const timeStops = [];
    const startTime = moment().add('m', minutes - moment().minute() % 15);

    while (startTime < endTime) {
        timeStops.push(new moment(startTime).format('HH:mm'));
        startTime.add('m', 15);
    }

    return timeStops;
}

  const [firstSelectOption, setFirstSelectOption] = useState(calculate(moment().add('h', 24), 15));
  const [secondSelectOption, setSecondSelectOption] = useState((calculate(moment().add('h', 24), 30)));


  const result = calculate(moment().add('h', 24));  




// La logique pour faire un seul handleChange qui va gérer les changements de valeur des trois composants : 

// dans le return de chaque composant, on va faire un onChange qui va appeler notre handleChange du type : 
// onChange:{(newDate) => {setDate(newDate) handleChange(newDate, afterhour, beforehour)}}

// et le handleChange, va gérer le fait de vérifier si on a bien une before > after et de générer les deux valeurs de date pour
// coller à la logique de l'autre composant. 

  // const handleChange = (newValue) => {
  //   if (!newValue) return;

  //   setValue(newValue);

  //   onChange({
  //     after: newValue[0],
  //     before: newValue[1]
  //   })
  // }
  
  const handleChange = (date, doneAfterHour, doneBeforeHour) => {
    if (!date || !doneAfterHour || !doneBeforeHour) return // à voir comment on vérifie

    // 
  }


  return (

    // ici on aura le datepicker et deux selects dans lesquels on fait passer des intervales. 

    <>
    <DatePicker
      style={{ width: '50%' }}
      format="LL"
      defaultValue={date} 
      onChange={(newDate) => {
        setDate(newDate)
        handleChange(newDate, doneAfterHour, doneBeforeHour)
      }}
      
    />
    <Select
      style={{ width: '25%' }}
      format={format}
      defaultValue={doneAfterHour} 
      onChange={(newDoneAfterHour) => {
        setDoneAfterHour(newDoneAfterHour)
        handleChange(date, newDoneAfterHour, doneBeforeHour)
      }}
      >
      {/* on va générer les options via la fonction pour les intervales */}
    </Select>
    <Select
      style={{ width: '25%' }}
      format={format}
      defaultValue={doneBeforeHour} 
      onChange={(newDoneBeforeHour) => {
        setDoneBeforeHour(newDoneBeforeHour)
        handleChange(date, doneAfterHour, newDoneBeforeHour)
      }}
      >
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

// La logique pour faire un seul handleChange qui va gérer les changements de valeur des trois composants : 

// dans le return de chaque composant, on va faire un onChange qui va appeler notre handleChange du type : 
// onChange:{(newDate) => {setDate(newDate) handleChange(newDate, afterhour, beforehour)}}

// et le handleChange, va gérer le fait de vérifier si on a bien une before > after et de générer les deux valeurs de date pour
// coller à la logique de l'autre composant. 