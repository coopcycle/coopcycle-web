import React, { Component } from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import Sticky from 'react-stickynode'
import _ from 'lodash'
import Modal from 'react-modal'

import AddressPicker from '../../components/AddressPicker'

import { changeAddress } from '../redux/actions'

class AddressModal extends Component {

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'enterAddress'])
  }

  closeModal() {
    // this.setState({ addressModalIsOpen: false })
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ this.props.t('ENTER_YOUR_ADDRESS') }
        className="ReactModal__Content--enter-address">
        <h4 className="text-center">{ this.props.titleText }</h4>
        <AddressPicker
          ref={ addressPicker => { this.modalAddressPicker = addressPicker } }
          autofocus
          address={ '' }
          geohash={ '' }
          onPlaceChange={ (value, address) => this.props.changeAddress(address) } />
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  const hasError = state.errors.hasOwnProperty('shippingAddress')

  return {
    isOpen: hasError,
    titleText: hasError ? _.first(state.errors.shippingAddress) : ''
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(AddressModal))
