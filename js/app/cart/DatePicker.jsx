import React, { Component } from 'react'
import _ from 'lodash'
import moment from 'moment'
import i18n from '../i18n'

moment.locale('fr')

class DatePicker extends Component {

  constructor(props) {
    super(props)

    const { availabilities, value } = this.props

    const days = _.groupBy(availabilities, date => moment(date).format('YYYY-MM-DD'))

    let availableTimes, date, time

    // we ignore the initial date if it is not in restaurant availabilities (in the past, set in another restaurant, aso)
    if (value && _.find(availabilities, (date) => moment(value).isSame(date))) {
      let day = moment(value).format('YYYY-MM-DD')
      availableTimes = days[day].map(date => moment(date).format('HH:mm'))
      const deliveryDateMoment = moment(value)
      date = deliveryDateMoment.format('YYYY-MM-DD')
      time = deliveryDateMoment.format('HH:mm')
    } else {
      availableTimes = days[Object.keys(days)[0]].map(date => moment(date).format('HH:mm'))
      const first = availabilities[0]
      const firstMoment = moment(first)
      date = firstMoment.format('YYYY-MM-DD')
      time = firstMoment.format('HH:mm')
    }

    this.state = {
      availableTimes,
      date,
      time
    }
  }

  onChangeDate({ target: { value }}, days) {
    this.setState({
      availableTimes: days[value].map(date => moment(date).format('HH:mm')),
    })
    this.props.onChange(value + ' ' + this.state.time + ':00')
  }

  onChangeTime({ target: { value }}) {
    this.props.onChange(this.state.date + ' ' + value + ':00')
  }

  render() {

    const { availabilities, value } = this.props
    const { availableTimes } = this.state
    let date, time

    if (!value) {
      const first = availabilities[0]
      const firstMoment = moment(first)
      date = firstMoment.format('YYYY-MM-DD')
      time = firstMoment.format('HH:mm')
    } else {
      const deliveryDateMoment = moment(value)
      date = deliveryDateMoment.format('YYYY-MM-DD')
      time = deliveryDateMoment.format('HH:mm')
    }

    const days = _.groupBy(availabilities, date => moment(date).format('YYYY-MM-DD'))
    const dates = _.keys(days)

    return (
      <div className="row" >
        <div className="col-sm-6" >
          <select value={ date }
                  className="form-control"
                  onChange={ evt => this.onChangeDate(evt, days) } >
            {
              dates.map(date => (
                <option key={ date } value={ date } >
                  { moment(date).calendar(null, { sameDay: i18n.t("DATEPICKER_TODAY"),  nextDay: i18n.t("DATEPICKER_TOMORROW"), nextWeek: 'dddd D MMM'}) }
                </option>
              ))
            }
          </select>
        </div>
        <div className="col-sm-6" >
          <select value={ time }
                  className="form-control"
                  onChange={ evt => this.onChangeTime(evt) } >
            {
              availableTimes.map(time => (
                <option key={ time } >
                  { time }
                </option>
              ))
            }
          </select>
        </div>
      </div>
    )
  }
}

export default DatePicker;
