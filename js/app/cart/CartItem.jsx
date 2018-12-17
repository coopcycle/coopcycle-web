import React from 'react'

class CartItem extends React.Component {

  renderAdjustments() {
    const { adjustments } = this.props

    if (adjustments.hasOwnProperty('menu_item_modifier')) {
      return (
        <div className="cart__item__adjustments">
          { adjustments.menu_item_modifier.map(adjustment =>
            <div key={ adjustment.id }>
              <small>{ adjustment.label }</small>
              <small className="pull-right">{ (adjustment.amount / 100).formatMoney(2, window.AppData.currencySymbol) }</small>
            </div>
          )}
        </div>
      )
    }
  }

  render() {

    let name = this.props.name
    if (name.length > 24) {
      name = name.substring(0, 23) + '…'
    }

    return (
      <div className="cart__item">
        <div className="cart__item__heading">
          <span>{name}</span>
          <span className="text-muted"> x {this.props.quantity}</span>
          <button type="button" className="close pull-right" aria-label="Close" onClick={() => this.props.onClickRemove()}>
            <span aria-hidden="true">×</span>
          </button>
          <span className="pull-right">{ (this.props.total / 100).formatMoney(2, window.AppData.currencySymbol) }</span>
        </div>
        { this.renderAdjustments() }
      </div>
    )
  }

}

export default CartItem
