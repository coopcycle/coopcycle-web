import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'

import { closeProductOptionsModal, addItemWithOptions, addItem } from '../redux/actions'
import ProductOptionsModalContent from './ProductOptionsModalContent'
import { ProductOptionsModalProvider } from './ProductOptionsModalContext'

class ProductOptionsModal extends Component {

  constructor(props) {
    super(props)
    this.formRef = React.createRef()
  }

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'showOptions'])
  }

  closeModal() {
    this.props.closeProductOptionsModal()
    window._paq.push(['trackEvent', 'Checkout', 'hideOptions'])
  }

  render() {

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onAfterOpen={ this.afterOpenModal.bind(this) }
        onRequestClose={ this.closeModal.bind(this) }
        shouldCloseOnOverlayClick={ true }
        contentLabel={ this.props.name }
        overlayClassName="ReactModal__Overlay--cart"
        className="ReactModal__Content--product-options"
        htmlOpenClassName="ReactModal__Html--open"
        bodyOpenClassName="ReactModal__Body--open">
        { this.props.isOpen &&
          <ProductOptionsModalProvider
            options={ this.props.options }
            price={ this.props.price }>
            <ProductOptionsModalContent
              ref={ this.formRef }
              name={ this.props.name }
              code={ this.props.code }
              options={ this.props.options }
              formAction={ this.props.formAction }
              images={ this.props.images }
              onClickClose={ this.props.closeProductOptionsModal }
              onSubmit={ (e) => {

                e.preventDefault()

                const $form = $(this.formRef.current)

                const data = $form.serializeArray()
                const quantity = $form.find('[data-product-quantity]').val() || 1

                if (data.length > 0) {
                  this.props.addItemWithOptions(this.props.formAction, data, quantity)
                } else {
                  this.props.addItem(this.props.formAction, quantity)
                }

                setTimeout(this.props.closeProductOptionsModal, 250)

              } } />
          </ProductOptionsModalProvider>
        }
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  return {
    isOpen:     state.isProductOptionsModalOpen,
    name:       state.productOptionsModalContext.name,
    options:    state.productOptionsModalContext.options,
    images:     state.productOptionsModalContext.images,
    price:      state.productOptionsModalContext.price,
    code:       state.productOptionsModalContext.code,
    formAction: state.productOptionsModalContext.formAction,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeProductOptionsModal: () => dispatch(closeProductOptionsModal()),
    addItem: (item, quantity) => dispatch(addItem(item, quantity)),
    addItemWithOptions: (item, data, quantity) => dispatch(addItemWithOptions(item, data, quantity)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(ProductOptionsModal))
