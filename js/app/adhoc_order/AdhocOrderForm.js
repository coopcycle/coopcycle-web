import React, { Component } from "react";
import { withTranslation } from "react-i18next";
import AdhocOrderItemModal from "./AdhocOrderItemModal";

class AdhocOrderForm extends Component {

  constructor(props) {
    super(props)

    this.state = {
      orderItemModalOpen: false,
      items: [],
    }

    this.openAdhocOrderItemModal = this.openAdhocOrderItemModal.bind(this)
    this.closeAdhocOrderItemModal = this.closeAdhocOrderItemModal.bind(this)
    this.onOrderItemSubmited = this.onOrderItemSubmited.bind(this)
    this._taxLabel = this._taxLabel.bind(this)
    this._renderItemsTotal = this._renderItemsTotal.bind(this)
  }

  openAdhocOrderItemModal() {
    this.setState({ orderItemModalOpen: true })
  }

  closeAdhocOrderItemModal() {
    this.setState({ orderItemModalOpen: false })
  }

  onOrderItemSubmited(item) {
    this.setState({items: this.state.items.concat(item)})
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
    const totalWithFormat = total ? (total / 100).formatMoney() : (0).formatMoney()
    return `${this.props.t('ADHOC_ORDER_ITEMS_TOTAL')} - ${totalWithFormat}`
  }

  render() {
    return (
      <div>
        <button className="btn btn-md btn-primary" onClick={ this.openAdhocOrderItemModal }>
          { this.props.t('ADHOC_ORDER_ADD_ITEM') }
        </button>

        {

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
              { this.state.items.map((item, key) =>
                <tr key={ key }>
                  <td className="text-blur">
                    <span>{ item.name }</span>
                  </td>
                  <td className="text-blur">
                    <span>{ this._taxLabel(item.taxCategory) }</span>
                  </td>
                  <td className="text-right">{ (item.price / 100).formatMoney() }</td>
                  <td className="text-right">
                    <a href="#" className="mx-4">
                      <i className="fa fa-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="#" className="mx-4">
                      <i className="fa fa-trash" aria-hidden="true"></i>
                    </a>
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
        }

        <AdhocOrderItemModal
          isOpen={ this.state.orderItemModalOpen }
          taxCategories={ this.props.taxCategories }
          closeModal={ this.closeAdhocOrderItemModal }
          onSubmitItem={ this.onOrderItemSubmited }>
        </AdhocOrderItemModal>
      </div>
    )
  }
}

export default withTranslation()(AdhocOrderForm)
