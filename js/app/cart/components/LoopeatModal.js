import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'

import { stopAskingToEnableReusablePackaging, toggleReusablePackaging } from '../redux/actions'
import LoopeatContext from '../../restaurant/loopeat'

const LoopeatModal = ({ isOpen, enableReusablePackaging }) => {

  const { t } = useTranslation()

  return (
    <Modal
      isOpen={ isOpen }
      shouldCloseOnOverlayClick={ false }
      // contentLabel={ this.props.t('ENTER_YOUR_ADDRESS') }
      overlayClassName="ReactModal__Overlay--cart"
      className="ReactModal__Content--enter-address"
      htmlOpenClassName="ReactModal__Html--open"
      bodyOpenClassName="ReactModal__Body--open">
      <div>
        <p className="mb-4">
        { t('CART_ZERO_WASTE_POPUP_TEXT', { name: LoopeatContext.name }) }
        </p>
        <div className="d-flex align-items-center justify-content-between">
          <a href={ LoopeatContext.customerAppUrl } target="_blank" rel="noreferrer">{ t('LEARN_MORE') }</a>
          <button type="button" className="btn btn-primary" onClick={ enableReusablePackaging }>{ t('I_UNDERSTAND') }</button>
        </div>
      </div>
    </Modal>
  )
}

function mapStateToProps(state) {

  if (!state.cart.restaurant.loopeatEnabled) {

    return {
      isOpen: false
    }
  }

  if (state.cart.reusablePackagingEnabled || state.cart.reusablePackagingQuantity === 0) {

    return {
      isOpen: false
    }
  }

  if (state.isAddressModalOpen) {

    return {
      isOpen: false
    }
  }

  return {
    isOpen: !state.isFetching && state.shouldAskToEnableReusablePackaging,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    enableReusablePackaging: () => {
      dispatch(toggleReusablePackaging(true))
      dispatch(stopAskingToEnableReusablePackaging())
    }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(LoopeatModal)
