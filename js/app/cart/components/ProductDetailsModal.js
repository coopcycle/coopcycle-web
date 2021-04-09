import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'

import { closeProductDetailsModal, queueAddItem } from '../redux/actions'
import ProductImagesCarousel from './ProductImagesCarousel'
import ProductModalHeader from './ProductModalHeader'

class ProductDetailsModal extends Component {

  afterOpenModal() {}

  closeModal() {
    this.props.closeProductDetailsModal()
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
          <form method="post" action={ this.props.formAction }
            onSubmit={ (e) => {
              e.preventDefault()
              this.props.queueAddItem(this.props.formAction, 1)
              setTimeout(this.props.closeProductDetailsModal, 250)
            }}
            className="product-modal-container">
            <ProductModalHeader name={ this.props.name }
              onClickClose={ this.props.closeProductDetailsModal } />
            <main>
              <ProductImagesCarousel images={ this.props.images } />
            </main>
            <footer className="p-4">
              <button className="btn btn-lg btn-block btn-primary" type="submit">
                { (this.props.price / 100).formatMoney() }
              </button>
            </footer>
          </form>
        }
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  return {
    isOpen:     state.isProductDetailsModalOpen,
    name:       state.productDetailsModalContext.name,
    images:     state.productDetailsModalContext.images,
    price:      state.productDetailsModalContext.price,
    formAction: state.productDetailsModalContext.formAction,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeProductDetailsModal: () => dispatch(closeProductDetailsModal()),
    queueAddItem: (item) => dispatch(queueAddItem(item)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(ProductDetailsModal))
