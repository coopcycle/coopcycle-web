import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import { changeAddress } from '../redux/actions'

const ADDRESS_TOO_FAR = 'Order::ADDRESS_TOO_FAR'

class AddressModal extends Component {

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'enterAddress'])
  }

  closeModal() {
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ this.props.t('ENTER_YOUR_ADDRESS') }
        overlayClassName="ReactModal__Overlay--overflow"
        className="ReactModal__Content--enter-address"
        htmlOpenClassName="ReactModal__Html--open"
        bodyOpenClassName="ReactModal__Body--open">
        <h4 className="text-center">{ this.props.titleText }</h4>
        <AddressAutosuggest
          addresses={ this.props.addresses }
          fuseOptions={{ threshold: 0.7, minMatchCharLength: 2 }}
          fuseSearchOptions={{ limit: 3 }}
          autofocus
          address={ '' }
          geohash={ '' }
          onAddressSelected={ (value, address) => this.props.changeAddress(address) } />
        { this.props.isAddressTooFar && (
          <div className="text-center">
            <a className="text-success" href={ window.Routing.generate('restaurants') }>
              { this.props.t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANTS') }
            </a>
          </div>
        ) }
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const hasError = state.errors.hasOwnProperty('shippingAddress')

  let titleText = ''
  let isAddressTooFar = false
  if (hasError) {

    const addressTooFarError = _.find(state.errors.shippingAddress, error => error.code === ADDRESS_TOO_FAR)
    if (addressTooFarError) {
      isAddressTooFar = true
    }

    titleText = _.first(state.errors.shippingAddress).message
  }

  return {
    isOpen: hasError,
    titleText,
    isAddressTooFar,
    addresses: state.addresses,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(AddressModal))
