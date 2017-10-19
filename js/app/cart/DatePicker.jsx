import React, { Component } from 'react'
import _ from 'lodash'
import moment from 'moment'

moment.locale('fr')

class DatePicker extends Component {

  constructor (props) {
    super(props)

    const { availabilities } = this.props

    const days = _.groupBy(availabilities, date =>
      moment(date).format('YYYY-MM-DD'))
    const availableTimes = days[Object.keys(days)[0]]
      .map(date => moment(date).format('HH:mm'))

    this.state = {
      availableTimes,
      date: null,
      time: null
    }
  }

  // 1. when shouldComponentUpdate said that ok,
  // the Component has to refresh given the new redux state
  // we can do the computation at WillMount and WillReceiveProps time
  // 2. we keep in the local
  // state of the component these computed date and time variables
  handleSetDateAndTime ({ availabilities, deliveryDate }) {
    let date, time
    if (!deliveryDate) {
      const first = availabilities[0]
      const firstMoment = moment(first)
      date = firstMoment.format('YYYY-MM-DD')
      time = firstMoment.format('HH:mm')
    } else {
      const deliveryDateMoment = moment(deliveryDate)
      date = deliveryDateMoment.format('YYYY-MM-DD')
      time = deliveryDateMoment.format('HH:mm')
    }
    this.setState({ date, time })
  }

  componentWillMount () {
    this.handleSetDateAndTime(this.props)
  }

  componentWillReceiveProps (nextProps) {
    // no need to compute if actually the component updates
    // for restaurant.availabilities changes
    if (nextProps.deliveryDate !== this.props.deliveryDate) {
      this.handleSetDateAndTime(nextProps)
    }
  }

  // 1. The Component still needs to trigger itself a new action
  // given its new local state, which is possible to control
  // at DidMount and DidUpdate time
  // 2. So once date and time are stored in the local state
  // we trigger the redux action setDeliveryDate, but we make sure
  // to call it once
  handleSetDeliveryDate () {
    const { date, time } = this.state
    this.props.setDeliveryDate(date + ' ' + time + ':00')
  }

  componentDidMount () {
    this.handleSetDeliveryDate()
  }

  componentDidUpdate (nextState) {
    // only trigger the action once
    // when date and/or time have changed in the local state
    if (nextState.date !== this.state.date || nextState.time !== this.state.time) {
      this.handleSetDeliveryDate()
    }
  }

  onChangeDate ({ target: { value }}, days) {
    this.setState({
      availableTimes: days[value].map(date =>
        moment(date).format('HH:mm')),
      date: value
    })
  }

  onChangeTime ({ target: { value }}) {
    this.setState({ time: value })
  }

  render() {

    const { availabilities } = this.props
    const { availableTimes, date, time } = this.state

    const days = _.groupBy(availabilities, date =>
      moment(date).format('YYYY-MM-DD'))
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
                  { moment(date).format('dddd DD MMM') }
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