import React, { Component } from "react";
import { withTranslation } from "react-i18next";
import { isValidPhoneNumber } from "react-phone-number-input";
import { connect } from "react-redux";
import Popconfirm from 'antd/lib/popconfirm'
import { Formik } from "formik";

import AdhocOrderItemModal from "./AdhocOrderItemModal";
import { getCountry } from "../i18n";
import { createAdhocOrder } from "./redux/actions";

const country = getCountry().toUpperCase()

class AdhocOrderForm extends Component {

  constructor(props) {
    super(props)

    this.state = {
      orderItemModalOpen: false,
      itemToEdit: null,
      itemToEditIndex: null,
      showSuccessMessage: false,
      showErrorMessage: false,
    }

    this.openAdhocOrderItemModal = this.openAdhocOrderItemModal.bind(this)
    this.closeAdhocOrderItemModal = this.closeAdhocOrderItemModal.bind(this)
    this.onOrderItemSubmited = this.onOrderItemSubmited.bind(this)
    this._taxLabel = this._taxLabel.bind(this)
    this._renderItemsTotal = this._renderItemsTotal.bind(this)
    this.onConfirmOrderItemDelete = this.onConfirmOrderItemDelete.bind(this)
    this.onEditPressed = this.onEditPressed.bind(this)

    this._validate = this._validate.bind(this)
    this._onSubmit = this._onSubmit.bind(this)
  }

  openAdhocOrderItemModal(e) {
    if (e) {
      e.preventDefault()
    }
    this.setState({ orderItemModalOpen: true })
  }

  closeAdhocOrderItemModal() {
    this.setState({
      orderItemModalOpen: false,
      itemToEdit: null,
      itemToEditIndex: null
    })
  }

  onOrderItemSubmited(item, values) {
    if (this.state.itemToEdit && null !== this.state.itemToEditIndex) {
      this._onEditedItemSubmited(item, values)
    } else {
      values.items.push(item)
    }
  }

  _onEditedItemSubmited(editedItem, values) {
    values.items = values.items.map((item, idx) => {
      if (idx === this.state.itemToEditIndex) {
        return editedItem
      }
      return item
    })
    this.setState({
      itemToEdit: null,
      itemToEditIndex: null
    })
  }

  _taxLabel(taxCode) {
    if (taxCode) {
      const tax = this.props.taxCategories.find(tax => tax.code === taxCode)
      return tax ? tax.name : taxCode
    }
  }

  _renderItemsTotal(values) {
    const total = values.items.reduce((acc, item) => acc + item.price, 0)
    const totalWithFormat = total ? total.formatMoney() : (0).formatMoney()
    return `${this.props.t('ADHOC_ORDER_ITEMS_TOTAL')} - ${totalWithFormat}`
  }

  onConfirmOrderItemDelete(itemToDeleteIndex, values, setFieldValue) {
    setFieldValue('items', values.items.filter((item, idx) => {
      if (idx !== itemToDeleteIndex) {
        return item
      }
    }))
  }

  onEditPressed(e, itemToEdit, itemToEditIndex) {
    e.preventDefault()
    this.setState({itemToEdit, itemToEditIndex})
    this.openAdhocOrderItemModal()
  }

  _toApiFormat(values) {
    return {
      restaurant: this.props.restaurant['@id'],
      items: values.items
        .filter(item => !item.existingItem)
        .map(({name, price, taxCategory}) => {
          return {
            name,
            taxCategory,
            price: price * 100
          }
        }),
      customer: {
        email: values.email,
        phoneNumber: values.phoneNumber,
        fullName: values.fullName
      }
    }
  }

  _validate(values) {
    let errors = {}

    if (!values.email || !/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i.test(values.email)) {
      errors.email = this.props.t('ADHOC_ORDER_CUSTOMER_EMAIL_ERROR')
    }

    if (!values.phoneNumber || !isValidPhoneNumber(values.phoneNumber, country)) {
      errors.phoneNumber = this.props.t('ADHOC_ORDER_CUSTOMER_PHONE_NUMBER_ERROR')
    }

    if (!values.fullName) {
      errors.fullName = this.props.t('ADHOC_ORDER_CUSTOMER_FULL_NAME_ERROR')
    }

    if (!values.items || values.items.length <= 0) {
      errors.items = this.props.t('ADHOC_ORDER_ITEMS_LIST_ERROR')
    }

    return errors
  }

  _onSubmit(values) {
    return this.props.createAdhocOrder(this._toApiFormat(values), this.props.existingOrderLoaded)
      .then(() => this.setState({showSuccessMessage: true}))
      .catch(() => this.setState({showErrorMessage: true}))
  }

  _loadSuccessMessage() {
    if (!this.props.isFetching && this.state.showSuccessMessage && this.props.order) {
      return (
        <div className="alert alert-success">
          { this.props.t('ADHOC_ORDER_SAVED_SUCCESSFULLY', {number: this.props.order.number}) }
        </div>
      )
    }
  }

  _loadItemsFromExistinOrder() {
    if (this.props.order && this.props.order.items?.length) {
      return this.props.order.items.map((item) => {
        return {
          name: item.name,
          price: item.unitPrice / 100,
          taxCategory: item.adjustments?.tax.length ? item.adjustments?.tax[0].label : null,
          existingItem: true,
        }
      })
    }

    return []
  }

  render() {

    const initialValues = {
      email: this.props.order?.customer?.email || "",
      phoneNumber: this.props.order?.customer?.phoneNumber || "",
      fullName: this.props.order?.customer?.fullName || "",
      items: this._loadItemsFromExistinOrder(),
    }

    return (
      <div>
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
            setFieldValue,
          }) => (
            <form onSubmit={ handleSubmit } autoComplete="off" className="form">
              {
                this.props.existingOrderLoaded &&
                (
                  <div>
                    <h4 className="title mb-4">
                      { this.props.t('ADD_PRODUCTS_TO_ORDER_TITLE', {number: this.props.order.number}) }
                    </h4>
                    <hr />
                  </div>
                )
              }
              <h4 className="title">{ this.props.t('ADHOC_ORDER_CUSTOMER_TITLE') }</h4>

              <div className="row">
                <div className={ `form-group col-md-6 ${errors.email ? 'has-error': ''}` }>
                  <label className="control-label" htmlFor="email">{ this.props.t('ADHOC_ORDER_CUSTOMER_EMAIL_LABEL') }</label>
                  <input type="text" name="email" className="form-control" autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    disabled={ this.props.isFetching || this.state.showSuccessMessage || this.props.existingOrderLoaded }
                    value={ values.email } />
                  { errors.email && touched.email && (
                    <small className="help-block">{ errors.email }</small>
                  ) }
                </div>
              </div>

              <div className="row">
                <div className={ `form-group col-md-6 ${errors.phoneNumber ? 'has-error': ''}` }>
                  <label className="control-label" htmlFor="phoneNumber">{ this.props.t('ADHOC_ORDER_CUSTOMER_PHONE_NUMBER_LABEL') }</label>
                  <input type="text" name="phoneNumber" className="form-control" autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    disabled={ this.props.isFetching || this.state.showSuccessMessage || this.props.existingOrderLoaded }
                    value={ values.phoneNumber } />
                  { errors.phoneNumber && touched.phoneNumber && (
                    <small className="help-block">{ errors.phoneNumber }</small>
                  ) }
                </div>
              </div>

              <div className="row">
                <div className={ `form-group col-md-6 ${errors.fullName ? 'has-error': ''}` }>
                  <label className="control-label" htmlFor="fullName">{ this.props.t('ADHOC_ORDER_CUSTOMER_FULL_NAME_LABEL') }</label>
                  <input type="text" name="fullName" className="form-control" autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    disabled={ this.props.isFetching || this.state.showSuccessMessage || this.props.existingOrderLoaded }
                    value={ values.fullName } />
                  { errors.fullName && touched.fullName && (
                    <small className="help-block">{ errors.fullName }</small>
                  ) }
                </div>
              </div>

              <hr />

              <h4 className="title">{ this.props.t('ADHOC_ORDER_ITEMS_LIST_TITLE') }</h4>

              <button type="button" className="btn btn-md btn-primary my-2"
                disabled={ this.props.isFetching || this.state.showSuccessMessage }
                onClick={ (e) => this.openAdhocOrderItemModal(e) } >
                { this.props.t('ADHOC_ORDER_ADD_ITEM') }
              </button>

              <table className="table table-condensed nomargin">
                <thead>
                  <tr>
                    <th>{ this.props.t('ADHOC_ORDER_ITEM_NAME_LABEL') }</th>
                    <th>{ this.props.t('ADHOC_ORDER_ITEM_TAXATION_LABEL') }</th>
                    <th>{ this.props.t('ADHOC_ORDER_ITEM_PRICE_LABEL') }</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  { values.items.map((item, index) =>
                    <tr key={ index }>
                      <td className="text-blur">
                        <span>{ item.name }</span>
                      </td>
                      <td className="text-blur">
                        <span>{ this._taxLabel(item.taxCategory) }</span>
                      </td>
                      <td className="text-right">{ item.price.formatMoney() }</td>
                      <td className="text-right">
                        <a role="button" href="#" className="text-reset mx-4"
                          disabled={item.existingItem}
                          onClick={ (e) => item.existingItem ? e.preventDefault() : this.onEditPressed(e, item, index) }>
                          <i className="fa fa-pencil"></i>
                        </a>
                        <Popconfirm
                          placement="left"
                          title={ this.props.t('ADHOC_ORDER_DELETE_ITEM_CONFIRM') }
                          onConfirm={ () => this.onConfirmOrderItemDelete(index, values, setFieldValue) }
                          okText={ this.props.t('CROPPIE_CONFIRM') }
                          disabled={item.existingItem}
                          cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }
                          >
                          <a role="button" href="#" className="text-reset"
                            disabled={item.existingItem}
                            onClick={ e => e.preventDefault() }>
                            <i className="fa fa-trash"></i>
                          </a>
                        </Popconfirm>
                      </td>
                    </tr>
                  ) }
                </tbody>
                <tfoot>
                  <tr>
                    <td className="text-right font-weight-bold" colSpan={3}>{ this._renderItemsTotal(values) }</td>
                    <td></td>
                  </tr>
                  { errors.items && touched.items && (
                    <tr>
                      <td className="has-error" colSpan={3}>
                        <span className="help-block">{ errors.items }</span>
                      </td>
                    </tr>
                  ) }
                </tfoot>
              </table>

              <hr />

              { this._loadSuccessMessage() }

              {
                !this.props.isFetching && this.state.showErrorMessage &&
                <div className="alert alert-danger">
                  { this.props.t('ADHOC_ORDER_FAILURE') }
                </div>
              }

              <div className="d-flex justify-content-between mt-4">
                <button type="submit" className="btn btn-md btn-success "
                  disabled={ this.props.isFetching || this.state.showSuccessMessage }>
                  { this.props.t('ADHOC_ORDER_SAVE_FINISH_ORDER') }
                </button>

                <button className="btn btn-md btn-info" type="button" onClick={this.props.onSearchOrderPressed}>
                  { this.props.t('SEARCH_EXISTING_ORDER') }
                </button>
              </div>


              <AdhocOrderItemModal
                isOpen={ this.state.orderItemModalOpen }
                taxCategories={ this.props.taxCategories }
                itemToEdit={ this.state.itemToEdit }
                closeModal={ this.closeAdhocOrderItemModal }
                onSubmitItem={ (item) => this.onOrderItemSubmited(item, values) }>
              </AdhocOrderItemModal>

            </form>
          )}

        </Formik>
      </div>
    )
  }
}

function mapStateToProps(state) {
  return {
    taxCategories: state.taxCategories,
    restaurant: state.restaurant,
    isFetching: state.isFetching,
    order: state.order,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    createAdhocOrder: (order, existingOrder) => dispatch(createAdhocOrder(order, existingOrder)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(AdhocOrderForm))
