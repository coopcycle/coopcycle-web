import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'

import { retryLastAddItemRequest } from '../redux/actions'

class RestaurantModal extends Component {

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'changeRestaurant'])
  }

  closeModal() {
  }

  renderModalContent() {

    const continueURL = window.Routing.generate('order_continue')

    return (
      <div>
        <div className="text-center">
          <p>
            { this.props.t('CART_CHANGE_RESTAURANT_MODAL_TEXT_LINE_1') }
            <br />
            { this.props.t('CART_CHANGE_RESTAURANT_MODAL_TEXT_LINE_2') }
          </p>
        </div>
        <div className="ReactModal__Restaurant__button">
          <a className="btn btn-default" href={ continueURL }>
            { this.props.t('CART_CHANGE_RESTAURANT_MODAL_BTN_NO') }
          </a>
          <button type="button" className="btn btn-primary" onClick={ () => this.props.retryLastAddItemRequest() }>
            { this.props.t('CART_CHANGE_RESTAURANT_MODAL_BTN_YES') }
          </button>
        </div>
      </div>
    )
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ this.props.t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
        className="ReactModal__Content--restaurant">
        { this.renderModalContent() }
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const hasError = Object.prototype.hasOwnProperty.call(state.errors, 'restaurant')

  return {
    isOpen: hasError,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    retryLastAddItemRequest: () => dispatch(retryLastAddItemRequest()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(RestaurantModal))
