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

    // Update DOM element
    const range = moment.range(this.state.value)
    const timeSlotInputValue = `${range.start.format('YYYY-MM-DD')} ${range.start.format('HH:mm')}-${range.end.format('HH:mm')}`
    document.querySelector(`[name="${this.props.timeSlotInputName}"]`).value = timeSlotInputValue

    this.props.changeDate(this.state.value)
    this.props.setDateModalOpen(false)
  }

  _onClickAsap(e) {
    e.preventDefault()

    this.props.clearDate()
    this.props.setDateModalOpen(false)
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ true }
        contentLabel={ this.props.t('CART_CHANGE_TIME_MODAL_LABEL') }
        className="ReactModal__Content--date">
        <form name="cart_time" className="p-4">
          <h4 className="text-center mb-4">{ this.props.t('CART_CHANGE_TIME_MODAL_TITLE') }</h4>
          <div className="text-center mb-4">
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
            <div className="text-center mb-4">
              <a href="#" className="ReactModal__Date__asap text-success" onClick={ this._onClickAsap.bind(this) }>
                { this.props.t('CART_DELIVERY_TIME_ASAP') }
              </a>
            </div>
          ) }
          <div className="divider"></div>
          <div className="flex gap-4">
            <button type="button" className="btn btn-block btn-default flex-1" onClick={ () => this.props.setDateModalOpen(false) }>
              { this.props.t('CART_DELIVERY_TIME_CANCEL') }
            </button>
            <button type="button" className="btn btn-block btn-success flex-2" onClick={ this._onClickSubmit.bind(this) } data-testid="cart.time.submit">
              { this.props.t('CART_DELIVERY_TIME_SUBMIT') }
            </button>
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
