import React, { Component } from "react";
import { withTranslation } from "react-i18next";
import Popconfirm from 'antd/lib/popconfirm'

import AdhocOrderItemModal from "./AdhocOrderItemModal";

class AdhocOrderForm extends Component {

  constructor(props) {
    super(props)

    this.state = {
      orderItemModalOpen: false,
      items: [],
      itemToEdit: null,
      itemToEditIndex: null,
    }

    this.openAdhocOrderItemModal = this.openAdhocOrderItemModal.bind(this)
    this.closeAdhocOrderItemModal = this.closeAdhocOrderItemModal.bind(this)
    this.onOrderItemSubmited = this.onOrderItemSubmited.bind(this)
    this._taxLabel = this._taxLabel.bind(this)
    this._renderItemsTotal = this._renderItemsTotal.bind(this)
    this.onConfirmOrderItemDelete = this.onConfirmOrderItemDelete.bind(this)
    this.onEditPressed = this.onEditPressed.bind(this)
    this.onSaveOrder = this.onSaveOrder.bind(this)
  }

  openAdhocOrderItemModal() {
    this.setState({ orderItemModalOpen: true })
  }

  closeAdhocOrderItemModal() {
    this.setState({ orderItemModalOpen: false })
  }

  onOrderItemSubmited(item) {
    if (this.state.itemToEdit && null !== this.state.itemToEditIndex) {
      this._onEditedItemSubmited(item)
    } else {
      this.setState({items: this.state.items.concat(item)})
    }
  }

  _onEditedItemSubmited(editedItem) {
    this.setState({
      items: this.state.items.map((item, idx) => {
        if (idx === this.state.itemToEditIndex) {
          return editedItem
        }
        return item
      }),
      itemToEdit: null,
      itemToEditIndex: null
    })
  }

  _taxLabel(taxCode) {
    if (taxCode) {
      return this.props.taxCategories.find(tax => tax.code === taxCode).name
    }
  }

  _itemsTotal() {
    return this.state.items.reduce((acc, item) => acc + item.price, 0)
  }

  _renderItemsTotal() {
    const total = this.state.items.reduce((acc, item) => acc + item.price, 0)
    const totalWithFormat = total ? total.formatMoney() : (0).formatMoney()
    return `${this.props.t('ADHOC_ORDER_ITEMS_TOTAL')} - ${totalWithFormat}`
  }

  onConfirmOrderItemDelete(itemToDeleteIndex) {
    this.setState({
      items: this.state.items.filter((item, idx) => {
        if (idx !== itemToDeleteIndex) {
          return item
        }
      }),
    })
  }

  onEditPressed(itemToEdit, itemToEditIndex) {
    this.setState({itemToEdit, itemToEditIndex})
    this.openAdhocOrderItemModal();
  }

  _itemsToApiFormat() {
    return this.state.items.map((item) => {
      return {
        ...item,
        price: item.price * 100
      }
    })
  }

  onSaveOrder() {
    this._itemsToApiFormat()
    return;
  }

  render() {
    return (
      <div>
        <button className="btn btn-md btn-primary" onClick={ this.openAdhocOrderItemModal }>
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
            { this.state.items.map((item, index) =>
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
                    onClick={ () => this.onEditPressed(item, index) }>
                    <i className="fa fa-pencil"></i>
                  </a>
                  <Popconfirm
                    placement="left"
                    title={ this.props.t('ADHOC_ORDER_DELETE_ITEM_CONFIRM') }
                    onConfirm={ () => this.onConfirmOrderItemDelete(index) }
                    okText={ this.props.t('CROPPIE_CONFIRM') }
                    cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }
                    >
                    <a role="button" href="#" className="text-reset"
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
              <td className="text-right font-weight-bold" colSpan={3}>{ this._renderItemsTotal() }</td>
              <td></td>
            </tr>
          </tfoot>
        </table>

        <button className="btn btn-md btn-success mt-4" onClick={ this.onSaveOrder }>
          { this.props.t('ADHOC_ORDER_SAVE_FINISH_ORDER') }
        </button>

        <AdhocOrderItemModal
          isOpen={ this.state.orderItemModalOpen }
          taxCategories={ this.props.taxCategories }
          itemToEdit={ this.state.itemToEdit }
          closeModal={ this.closeAdhocOrderItemModal }
          onSubmitItem={ this.onOrderItemSubmited }>
        </AdhocOrderItemModal>
      </div>
    )
  }
}

export default withTranslation()(AdhocOrderForm)
