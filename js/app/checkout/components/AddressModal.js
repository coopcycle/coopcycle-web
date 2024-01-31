import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'
import classNames from 'classnames'
import ngeohash from 'ngeohash'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import { changeAddress, closeAddressModal, enableTakeaway } from '../redux/actions'
import { selectIsCollectionEnabled } from '../redux/selectors'

const ADDRESS_TOO_FAR = 'Order::ADDRESS_TOO_FAR'
const ADDRESS_NOT_PRECISE = 'Order::ADDRESS_NOT_PRECISE'

class AddressModal extends Component {

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'enterAddress'])
  }

  closeModal() {
  }

  renderBackButton() {
    if (this.props.restaurant) {
      // if user is in a restaurant page we should just keep the user in that page
      return (
        <a className="text-muted" href="#" onClick={ (e) => {
            e.preventDefault();
            this.props.closeAddressModal();
          }}>
          <i className="fa fa-arrow-left mr-2"></i>
          <span>{ this.props.t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANT') }</span>
        </a>
      )
    }

    let params = {}
    if (this.props.isAddressTooFar && this.props.shippingAddress?.geo) {
      const { latitude, longitude } = this.props.shippingAddress.geo
      const geohash = ngeohash.encode(latitude, longitude, 11)
      params = {
        ...params,
        geohash
      }
    }

    return (
      <a className="text-muted" href={window.Routing.generate('restaurants', params)}>
        <i className="fa fa-arrow-left mr-2"></i>
        <span>{ this.props.t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANTS') }</span>
      </a>
    )
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ this.props.t('ENTER_YOUR_ADDRESS') }
        overlayClassName="ReactModal__Overlay--cart"
        className="ReactModal__Content--enter-address"
        htmlOpenClassName="ReactModal__Html--open"
        bodyOpenClassName="ReactModal__Body--open">
        <header className="d-flex align-items-center justify-content-between mb-5">
          { this.renderBackButton() }
          <button type="button" className="close pl-4" onClick={ this.props.closeAddressModal }>
            <i className="fa fa-close"></i>
          </button>
        </header>
        <div>
          <span className={ classNames({
            'text-center': true,
            'd-block': true,
            'mb-3': true,
            'text-danger': this.props.isError })
          }>
            { this.props.helpText }
          </span>
          <AddressAutosuggest
            id="modal"
            addresses={ this.props.addresses }
            fuseOptions={{ threshold: 0.7, minMatchCharLength: 2 }}
            fuseSearchOptions={{ limit: 3 }}
            // We use autofocus only when the are no saved addresses,
            // to avoid having the suggestions list opened automatically
            autofocus={ this.props.addresses.length === 0 }
            address={ '' }
            onAddressSelected={ (value, address) => this.props.changeAddress(address) }
            error={ this.props.isError } />
        </div>
        <button
          type="button"
          className={ classNames({
            'btn': true,
            'btn-default': true,
            'visible': this.props.isCollectionEnabled,
            'invisible': !this.props.isCollectionEnabled,
          }) }
          onClick={ this.props.enableTakeaway }>
          { this.props.t('CART_ADDRESS_MODAL_NO_THANKS_TAKEAWAY') }
        </button>
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const hasError = Object.prototype.hasOwnProperty.call(state.errors, 'shippingAddress')

  let helpText = ''
  let isError = false
  let isAddressTooFar = false

  if (hasError) {

    const errorCodes =
      state.errors.shippingAddress.map(error => error.code)

    isAddressTooFar =
      _.includes(errorCodes, ADDRESS_TOO_FAR)

    isError =
      isAddressTooFar || _.includes(errorCodes, ADDRESS_NOT_PRECISE)

    helpText = _.first(state.errors.shippingAddress).message

  }

  return {
    isOpen: state.isAddressModalOpen,
    helpText,
    isError,
    isAddressTooFar,
    addresses: state.addresses,
    isCollectionEnabled: selectIsCollectionEnabled(state),
    shippingAddress: state.cart.shippingAddress,
    restaurant: state.addressModalContext.restaurant,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
    closeAddressModal: () => dispatch(closeAddressModal()),
    enableTakeaway: () => dispatch(enableTakeaway()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(AddressModal))
