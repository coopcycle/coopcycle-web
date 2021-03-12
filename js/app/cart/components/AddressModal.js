import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import { changeAddress, closeAddressModal, enableTakeaway } from '../redux/actions'
import { selectIsCollectionEnabled } from '../redux/selectors'

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
        <button type="button" className="close" onClick={ this.props.closeAddressModal }>
          <i className="fa fa-close"></i>
        </button>
        <h4 className="text-center">{ this.props.titleText }</h4>
        <AddressAutosuggest
          id="modal"
          addresses={ this.props.addresses }
          fuseOptions={{ threshold: 0.7, minMatchCharLength: 2 }}
          fuseSearchOptions={{ limit: 3 }}
          autofocus
          address={ '' }
          onAddressSelected={ (value, address) => this.props.changeAddress(address) } />
        { this.props.isAddressTooFar && (
          <div className="text-center">
            <a className="text-success" href={ window.Routing.generate('restaurants') }>
              { this.props.t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANTS') }
            </a>
          </div>
        ) }
        { this.props.isCollectionEnabled && (
          <button type="button" className="btn btn-default" onClick={ () => this.props.enableTakeaway() }>
            { this.props.t('CART_ADDRESS_MODAL_NO_THANKS_TAKEAWAY') }
          </button>
        ) }
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const hasError = Object.prototype.hasOwnProperty.call(state.errors, 'shippingAddress')

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
    isOpen: state.isAddressModalOpen,
    titleText,
    isAddressTooFar,
    addresses: state.addresses,
    isCollectionEnabled: selectIsCollectionEnabled(state),
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
