import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import Form from 'antd/lib/form'
const FormItem = Form.Item

import Button from 'antd/lib/button'
import DatePicker from 'antd/lib/date-picker'
import TimePicker from 'antd/lib/time-picker'
import LocaleProvider from 'antd/lib/locale-provider'
import frBE from 'antd/lib/locale-provider/fr_BE'

const today = moment().startOf('day')

const dateFormat = 'DD/MM/YYYY';
const timeFormat = 'HH:mm';

let minutes = [];
for (let i = 0; i <= 60; i++) {
  if (0 !== i % 15) {
    minutes.push(i)
  }
}

class DateTimePicker extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      value: this.props.defaultValue,
    };
  }

  componentDidMount() {
    this.props.onChange(this.state.value)
  }

  onDateChange(date, dateString) {

    const { value } = this.state;

    value.set('date', date.get('date'));
    value.set('month', date.get('month'));
    value.set('year', date.get('year'));

    this.setState({ value })

    this.props.onChange(value)
  }

  onTimeChange(date, timeString) {

    const { value } = this.state

    value.set('hour', date.get('hour'))
    value.set('minute', date.get('minute'))
    value.set('second', 0)

    this.setState({ value })

    this.props.onChange(value)
  }

  disabledDate(date) {
    if (date) {
      return date.isBefore(today)
    }
  }

  disabledMinutes(h) {
    return minutes
  }

  render() {

    const formItemProps = this.props.error ? {
      validateStatus: "error",
    } : {}

    return (
      <div>
        <FormItem {...formItemProps}>
          <LocaleProvider locale={frBE}>
            <DatePicker
              disabledDate={this.disabledDate}
              onChange={this.onDateChange.bind(this)}
              format={dateFormat}
              placeholder="Date"
              defaultValue={this.props.defaultValue}
            />
          </LocaleProvider>
          <LocaleProvider locale={frBE}>
            <TimePicker
              disabledMinutes={this.disabledMinutes}
              onChange={this.onTimeChange.bind(this)}
              defaultValue={this.props.defaultValue}
              format={timeFormat}
              hideDisabledOptions
              placeholder="Heure"
              addon={panel => (
                <Button size="small" type="primary" onClick={() => panel.close()}>OK</Button>
              )}
            />
          </LocaleProvider>
        </FormItem>
      </div>
    )
  }
}

export default (el, options) => {

  render(<DateTimePicker
    defaultValue={ moment(options.defaultValue) }
    onChange={ options.onChange } />, el)
}
