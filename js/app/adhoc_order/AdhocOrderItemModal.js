import { Formik } from "formik";
import React, { Component } from "react";
import { withTranslation } from "react-i18next";
import Modal from 'react-modal'

import './index.scss'

class AdhocOrderItemModal extends Component {

  constructor(props) {
    super(props)

    this._validate = this._validate.bind(this)
    this._onSubmit = this._onSubmit.bind(this)
    this._closeModal = this._closeModal.bind(this)
  }

  _validate(values) {
    let errors = {}

    if (!values.name) {
      errors.name = this.props.t('ADHOC_ORDER_ITEM_NAME_ERROR')
    }

    if (values.price <= 0) {
      errors.price = this.props.t('ADHOC_ORDER_ITEM_PRICE_ERROR')
    }

    if (!values.taxCategory) {
      errors.taxCategory = this.props.t('ADHOC_ORDER_ITEM_TAXATION_ERROR')
    }

    return errors
  }

  _onSubmit(values) {
    this.props.onSubmitItem(values)
    this.props.closeModal()
  }

  _closeModal() {
    this.props.closeModal()
  }

  render() {

    const initialValues = this.props.itemToEdit || {
      name: "",
      price: 0,
      taxCategory: "",
    }

    return (
      <Modal
        isOpen={ this.props.isOpen }
        onRequestClose={ this._closeModal }
        shouldCloseOnOverlayClick={ false }
        className="ReactModal__Content--adhoc-order-item"
        overlayClassName="ReactModal__Overlay--adhoc-order-item">
        <Formik
          initialValues={ initialValues }
          validate={ this._validate }
          onSubmit={ this._onSubmit }
          validateOnBlur={ false }
          validateOnChange={ false }>
          {({
            values,
            errors,
            touched,
            handleSubmit,
            handleChange,
            handleBlur,
          }) => (
            <div>
              <div className="modal-header">
                <button type="button" className="close" onClick={ this._closeModal } aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
                <h4 className="modal-title" id="user-modal-label">{ this.props.t('ADHOC_ORDER_ITEM_TITLE') }</h4>
              </div>
              <form onSubmit={ handleSubmit } autoComplete="off" className="modal-body form">
                <div className={ errors.name && touched.name ? 'form-group has-error' : 'form-group' }>
                  <label className="control-label" htmlFor="name">{ this.props.t('ADHOC_ORDER_ITEM_NAME_LABEL') }</label>
                  <input type="text" name="name" className="form-control" autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.name } />
                  { errors.name && touched.name && (
                    <small className="help-block">{ errors.name }</small>
                  ) }
                </div>

                <div className={ errors.price && touched.price ? 'form-group has-error' : 'form-group' }>
                  <label className="control-label" htmlFor="price">{ this.props.t('ADHOC_ORDER_ITEM_PRICE_LABEL') }</label>
                  <input type="number" step={0.01} min={0} name="price" className="form-control" autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.price } />
                  { errors.price && touched.price && (
                    <small className="help-block">{ errors.price }</small>
                  ) }
                </div>

                <div className={ errors.taxCategory && touched.taxCategory ? 'form-group has-error' : 'form-group' }>
                  <label className="control-label" htmlFor="taxCategory">{ this.props.t('ADHOC_ORDER_ITEM_TAXATION_LABEL') }</label>
                  <select name="taxCategory" value={ values.taxCategory} onChange={ handleChange } className="form-control" onBlur={ handleBlur }>
                    <option value="">-</option>
                    {
                      this.props.taxCategories.map((item) => {
                        return <option key={item.code} value={item.code}>{item.name}</option>
                      })
                    }
                  </select>
                  { errors.taxCategory && touched.taxCategory && (
                    <small className="help-block">{ errors.taxCategory }</small>
                  ) }
                </div>

                <button type="submit" className="btn btn-md btn-block btn-primary">{ this.props.t('ADHOC_ORDER_SAVE') }</button>
              </form>
            </div>
          )}
        </Formik>
      </Modal>
    )
  }
}

export default withTranslation()(AdhocOrderItemModal)
