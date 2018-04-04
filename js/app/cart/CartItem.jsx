import React from 'react'
import numeral  from 'numeral'
import 'numeral/locales'

const locale = $('html').attr('lang')

numeral.locale(locale)

class CartItem extends React.Component {

  renderAdjustments() {
    const { adjustments } = this.props

    if (adjustments.hasOwnProperty('menu_item_modifier')) {
      return (
        <div className="text-muted">
          { adjustments.menu_item_modifier.map(adjustment =>
            <div key={ adjustment.id }>
              <small>{ adjustment.label }</small>
              <small className="pull-right">{ numeral(adjustment.amount / 100).format('0,0.00 $') }</small>
            </div>
          )}
        </div>
      )
    }
  }

  render() {

    let name = this.props.name;
    if (name.length > 24) {
      name = name.substring(0, 23) + '…'
    }

    return (
      <li className="list-group-item">
        <span>{name}</span>
        <span className="text-muted"> x {this.props.quantity}</span>
        <button type="button" className="close pull-right" aria-label="Close" onClick={(e) => this.props.cart.removeItem(this)}>
          <span aria-hidden="true">×</span>
        </button>
        <span className="pull-right">{ numeral(this.props.total / 100).format('0,0.00 $') }</span>
        { this.renderAdjustments() }
      </li>
    );
  }

}

module.exports = CartItem;
