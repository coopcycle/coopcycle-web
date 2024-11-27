import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterMoment } from '@mui/x-date-pickers/AdapterMoment'
import { DateTimeRangePicker } from '@mui/x-date-pickers-pro/DateTimeRangePicker';
import { SingleInputDateTimeRangeField } from '@mui/x-date-pickers-pro/SingleInputDateTimeRangeField';


class DateRangePicker extends React.Component {

  constructor(props) {
    super(props)

    let value = []
    if (this.props.defaultValue) {
      value = [moment(this.props.defaultValue.after), moment(this.props.defaultValue.before)]
    }
    
    
    this.state = {
     value
   }
    // si on passe par la classe, on doit bind le onChange à l'instance
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

    this.setState(value)

    this.props.onChange(values)
  }

  render() {

    return (
      <div>
        <DateTimeRangePicker 
          // slots={{ field: SingleInputDateTimeRangeField }}
          defaultValue={this.state.value}
          onChange={(value) => this.onChange(value)}
          format={this.props.format}
           />
      </div>    
    )
  }

}
export default function(el, options) {
  const defaultProps = {
    onChange: () => {},
    format: 'LLL',
  };

  const props = { ...defaultProps, ...options };

  render(
    <LocalizationProvider dateAdapter={AdapterMoment}>
      <DateRangePicker {...props} />
    </LocalizationProvider>,
    el
  );
}