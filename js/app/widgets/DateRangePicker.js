import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker } from 'antd';

import { timePickerProps } from '../utils/antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

class DateRangePicker extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      after: this.props.defaultValue.after,
      before: this.props.defaultValue.before,
    }

    this.onChange = this.onChange.bind(this)
  }

  onChange(value) {
    // When the input has been cleared
    if (!value) {
      return
    }

    const values = {
      after: value[0],
      before: value[1],
    }

    this.setState(values)

    this.props.onChange(values)
  }

  render() {

    return (
      <DatePicker.RangePicker
        style={{ width: '100%' }}
        showTime={{
          ...timePickerProps,
          hideDisabledOptions: true,
        }}
        format="LLL"
        defaultValue={[ moment(this.state.after), moment(this.state.before) ]}
        onChange={(value) => this.onChange(value)} />
    )
  }
}

export default function(el, options) {

  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {}
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={ antdLocale }>
      <DateRangePicker { ...props } />
    </ConfigProvider>, el)
}
