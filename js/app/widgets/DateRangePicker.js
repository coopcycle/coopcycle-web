import  React, {useState } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker } from 'antd';

import { timePickerProps } from '../utils/antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const DateRangePicker = ({ defaultValue, onChange, format, showTime }) => {
  console.error('valeurs', defaultValue)

  const [value, setValue] = useState(() =>
    defaultValue
      ? [moment(defaultValue.after), moment(defaultValue.before)]
      : [],
  )

  const handleDateChange = newValue => {
    if (!newValue) return

    onChange({
      after: newValue[0],
      before: newValue[1],
    })

    setValue(newValue)
  }

  let props = {}
  if (showTime) {
    props = {
      showTime: {
        ...timePickerProps,
        hideDisabledOptions: true,
      },
    }
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