import React from 'react'
import { translate } from 'react-i18next'

const hasAdjustments = (item) => item.adjustments.hasOwnProperty('menu_item_modifier') && item.adjustments['menu_item_modifier'].length > 0

class OrderItems extends React.Component {

  renderOrderItemAdjustments(item) {
    return (
      <ul className="list-unstyled">
        { item.adjustments['menu_item_modifier'].map((adjustment) =>
          <li key={ adjustment.id }>
            <small className="text-muted">{ adjustment.label }</small>
          </li>
        ) }
      </ul>
    )
  }

  render() {

    const { order } = this.props

    return (
      <table className="table table-condensed nomargin">
        <tbody>
          { order.items.map((item, key) =>
            <tr key={ key }>
              <td>
                <span>{ item.quantity } x { item.name }</span>
                { hasAdjustments(item) && ( <br /> ) }
                { hasAdjustments(item) && this.renderOrderItemAdjustments(item) }
              </td>
              <td className="text-right">{ (item.total / 100).formatMoney(2, window.AppData.currencySymbol) }</td>
            </tr>
          ) }
        </tbody>
      </table>
    )
  }

}

export default translate()(OrderItems)
