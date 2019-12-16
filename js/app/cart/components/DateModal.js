import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'

import DatePicker from './DatePicker'
import { clearDate, changeDate, setDateModalOpen } from '../redux/actions'

class DateModal extends Component {

  constructor(props) {
    super(props)
    this.state = {
      date: null
    }
  }

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'changeDate'])
  }

  closeModal() {
  }

  componentDidUpdate(prevProps) {
    if (this.props.isOpen && !prevProps.isOpen) {
      this.setState({ date: this.value })
    }
  }

  componentDidMount() {
    this.setState({ date: this.value })
  }

  _updateDate(dateString) {
    this.setState({ date: dateString })
  }

  _onClickSubmit() {
    this.props.changeDate(this.state.date)
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
        <form name="cart_time">
          <h4 className="text-center">{ this.props.t('CART_CHANGE_TIME_MODAL_TITLE') }</h4>
          <div className="text-center">
            <DatePicker
              dateInputName={ this.props.datePickerDateInputName }
              timeInputName={ this.props.datePickerTimeInputName }
              availabilities={ this.props.availabilities }
              value={ this.props.value }
              onChange={ (dateString) => this._updateDate(dateString) } />
          </div>
          { !this.props.isAsap && (
            <div className="text-center">
              <a className="ReactModal__Date__asap text-success" onClick={ this._onClickAsap.bind(this) }>
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
              <button type="button" className="btn btn-block btn-success" onClick={ this._onClickSubmit.bind(this) }>
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

  const value = !!state.cart.shippedAt ? state.cart.shippedAt : _.first(state.availabilities)

  return {
    isOpen: state.isDateModalOpen,
    availabilities: state.availabilities,
    datePickerDateInputName: state.datePickerDateInputName,
    datePickerTimeInputName: state.datePickerTimeInputName,
    isAsap: !!state.cart.shippedAt ? false : true,
    value,
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
