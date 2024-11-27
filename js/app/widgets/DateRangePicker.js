import  React, {useState } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker } from 'antd';

import { timePickerProps } from '../utils/antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

// class DateRangePicker extends React.Component {

//   constructor(props) {
//     super(props)

//     let value = []
//     if (this.props.defaultValue) {
//       value = [moment(this.props.defaultValue.after), moment(this.props.defaultValue.before)]
//     }

//     this.state = {
//       value
//     }
//     // si on passe par la classe, on doit bind le onChange à l'instance
//     this.onChange = this.onChange.bind(this)
//   }

//   onChange(value) {
//     // When the input has been cleared
//     if (!value) {
//       return
//     }

//     const values = {
//       after: value[0],
//       before: value[1],
//     }

//     this.setState(value)

//     this.props.onChange(values)
//   }

//   render() {

//     let props = {}
//     if (this.props.showTime) {
//       props = {showTime: {
//       ...timePickerProps,
//           hideDisabledOptions: true,
//       }}
//     }

//     return (
//       <DatePicker.RangePicker
//         style={{ width: '100%' }}
//         format={this.props.format}
//         defaultValue={this.state.value}
//         onChange={(value) => this.onChange(value)}
//         {...props}
//     />
//     )
//   }
// }

// export default function(el, options) {

//   const defaultProps = {
//     getDatePickerContainer: null,
//     getTimePickerContainer: null,
//     onChange: () => {},
//     format: 'LLL',
//   }

//   const props = { ...defaultProps, ...options }

//   render(
//     <ConfigProvider locale={ antdLocale }>
//       <DateRangePicker { ...props } />
//     </ConfigProvider>, el)
// }

const DateRangePicker = ({ defaultValue, onChange, format, showTime }) => {

  console.error("valeurs", defaultValue);
  

  
  const [value, setValue] = useState(() => defaultValue ? [moment(defaultValue.after), moment(defaultValue.before)] : [])

  const handleDateChange = (newValue) => {
    if (!newValue) return; 

    onChange({
      after: newValue[0],
      before: newValue[1]
    })

    setValue(newValue); 
  }

  let props = {}
  if (showTime) {
    props = {showTime: {
    ...timePickerProps,
        hideDisabledOptions: true,
    }}
  }
  return (
    <DatePicker.RangePicker
      style={{ width: '100%' }}
      format={format}
      defaultValue={value}
      onChange={handleDateChange}
      {...props}
    />
    )
  }

export default function(el, options) {

  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {},
    format: 'LLL',
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={ antdLocale }>
      <DateRangePicker { ...props } />
    </ConfigProvider>, el)
}