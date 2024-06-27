import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'
import Moment from 'moment'
import { extendMoment } from 'moment-range'

import { clearDate, changeDate, setDateModalOpen } from '../redux/actions'
import { selectCartTiming } from '../redux/selectors'
import DatePicker from '../../components/order/DatePicker'
import TimeSlotPicker from '../../components/order/TimeSlotPicker'

const moment = extendMoment(Moment)

class DateModal extends Component {

  constructor(props) {
    super(props)
    this.state = {
      value: props.value
    }
  }

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'changeDate'])
  }

  closeModal() {
  }

  componentDidUpdate(prevProps) {
    if (this.props.isOpen && !prevProps.isOpen) {
      this.setState({ value: this.props.value })
    }
  }

  componentDidMount() {
    this.setState({ value: this.props.value })
  }

  _onClickSubmit() {
    this.props.changeDate(this.state.value)
    this.props.setDateModalOpen(false)
  }

  _onClickAsap(e) {
    e.preventDefault()

    this.props.clearDate()
    this.props.setDateModalOpen(false)
  }

  render() {

    const range = moment.range(this.state.value)
    const timeSlotInputValue = `${range.start.format('YYYY-MM-DD')} ${range.start.format('HH:mm')}-${range.end.format('HH:mm')}`

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ true }
        contentLabel={ this.props.t('CART_CHANGE_TIME_MODAL_LABEL') }
        className="ReactModal__Content--date">
        <form name="cart_time">
          <input type="hidden" name={ this.props.timeSlotInputName } value={ timeSlotInputValue } />
          <h4 className="text-center">{ this.props.t('CART_CHANGE_TIME_MODAL_TITLE') }</h4>
          <div className="text-center">
            { this.props.behavior === 'time_slot' && (
              <TimeSlotPicker
                choices={ this.props.ranges }
                value={ this.props.value }
                onChange={ value => this.setState({ value }) } />
            )}
            { this.props.behavior === 'asap' && (
              <DatePicker
                choices={ this.props.ranges }
                value={ this.props.value }
                onChange={ value => this.setState({ value }) } />
            )}
          </div>
          { this.props.isPreOrder && (
            <div className="text-center">
              <a href="#" className="ReactModal__Date__asap text-success" onClick={ this._onClickAsap.bind(this) }>
                { this.props.t('CART_DELIVERY_TIME_ASAP') }
              </a>
            </div>
          ) }
          <hr />
          <div className="row" >
            <div className="col-sm-4 col-xs-6">
              <button type="button" className="btn btn-block btn-default" onClick={ () => this.props.setDateModalOpen(false) }>
                { this.props.t('CART_DELIVERY_TIME_CANCEL') }
              </button>
            </div>
            <div className="col-sm-8 col-xs-6">
              <button type="button" className="btn btn-block btn-success" onClick={ this._onClickSubmit.bind(this) } data-testid="cart.time.submit">
                { this.props.t('CART_DELIVERY_TIME_SUBMIT') }
              </button>
            </div>
          </div>
        </form>
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const isPreOrder =
    false === _.isEmpty(state.cart.shippingTimeRange)

  const cartTiming = selectCartTiming(state)

  const value =
    isPreOrder ? state.cart.shippingTimeRange : _.first(cartTiming.ranges)

  return {
    isOpen: state.isDateModalOpen,
    timeSlotInputName: state.datePickerTimeSlotInputName,
    isPreOrder,
    value,
    ranges: cartTiming.ranges,
    behavior: cartTiming.behavior,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    clearDate: () => dispatch(clearDate()),
    changeDate: date => dispatch(changeDate(date)),
    setDateModalOpen: isOpen => dispatch(setDateModalOpen(isOpen)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(DateModal))
