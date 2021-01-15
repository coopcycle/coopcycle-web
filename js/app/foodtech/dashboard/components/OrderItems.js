import React from 'react'
import { withTranslation } from 'react-i18next'

const hasAdjustments = (item) => {
  const hasOptions = Object.prototype.hasOwnProperty.call(item.adjustments, 'menu_item_modifier') && item.adjustments['menu_item_modifier'].length > 0
  const hasPackaging = Object.prototype.hasOwnProperty.call(item.adjustments, 'reusable_packaging') && item.adjustments['reusable_packaging'].length > 0

  return hasOptions || hasPackaging
}

class OrderItems extends React.Component {

  renderOrderItemAdjustments(item) {

    let adjustments = []

    if (Object.prototype.hasOwnProperty.call(item.adjustments, 'menu_item_modifier')) {
      adjustments = adjustments.concat(item.adjustments['menu_item_modifier'])
    }

    if (Object.prototype.hasOwnProperty.call(item.adjustments, 'reusable_packaging')) {
      adjustments = adjustments.concat(item.adjustments['reusable_packaging'])
    }

    return (
      <ul className="list-unstyled">
        { adjustments.map((adjustment) =>
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
              <td className="text-right">{ (item.total / 100).formatMoney() }</td>
            </tr>
          ) }
        </tbody>
      </table>
    )
  }

}

export default withTranslation()(OrderItems)
