import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Form, TimePicker } from 'antd';

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const today = moment().startOf('day')

const dateFormat = 'DD/MM/YYYY'
const timeFormat = 'HH:mm'

let minutes = []
for (let i = 0; i <= 60; i++) {
  if (0 !== i % 15) {
    minutes.push(i)
  }
}

class DateTimePicker extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      value: this.props.defaultValue,
    }
  }

  onDateChange(date) {

    // When the input has been cleared
    if (!date) {
      return
    }

    let { value } = this.state

    if (!value) {
      value = moment()
    }

    value.set('date', date.get('date'))
    value.set('month', date.get('month'))
    value.set('year', date.get('year'))

    this.setState({ value })

    this.props.onChange(value)
  }

  onTimeChange(date) {

    // When the input has been cleared
    if (!date) {
      return
    }

    let { value } = this.state

    if (!value) {
      value = moment()
    }

    value.set('hour', date.get('hour'))
    value.set('minute', date.get('minute'))
    value.set('second', 0)

    this.setState({ value })

    this.props.onChange(value)
  }

  disabledDate(date) {
    if (date && !this.props.allowPastDates) {
      return date.isBefore(today)
    }
  }

  disabledMinutes() {
    return minutes
  }

  render() {

    const formItemProps = this.props.error ? {
      hasFeedback: true,
      validateStatus: 'error',
    } : {}

    let datePickerProps = {}
    if (Object.prototype.hasOwnProperty.call(this.props, 'getDatePickerContainer') && typeof this.props.getDatePickerContainer === 'function') {
      datePickerProps = {
        getCalendarContainer: this.props.getDatePickerContainer
      }
    }

    let timePickerProps = {}
    if (Object.prototype.hasOwnProperty.call(this.props, 'getTimePickerContainer') && typeof this.props.getTimePickerContainer === 'function') {
      timePickerProps = {
        getPopupContainer: this.props.getTimePickerContainer
      }
    }

    return (
      <div>
        <Form.Item {...formItemProps}>
          <DatePicker
            disabledDate={this.disabledDate.bind(this)}
            onChange={this.onDateChange.bind(this)}
            format={dateFormat}
            placeholder="Date"
            defaultValue={this.props.defaultValue}
            { ...datePickerProps }
          />
          <TimePicker
            disabledMinutes={this.disabledMinutes}
            onChange={this.onTimeChange.bind(this)}
            defaultValue={this.props.defaultValue}
            format={timeFormat}
            hideDisabledOptions
            placeholder="Heure"
            { ...timePickerProps }
          />
        </Form.Item>
      </div>
    )
  }
}

export default function(el, options) {

  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {}
  }

  if (null !== options.defaultValue) {
    options.defaultValue = moment(options.defaultValue)
  } else {
    delete options.defaultValue
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={ antdLocale }>
      <DateTimePicker { ...props } />
    </ConfigProvider>, el)
}
