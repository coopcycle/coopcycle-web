import React from 'react'
import { withTranslation } from 'react-i18next'

class OrderTotal extends React.Component {
  render() {

    const { order } = this.props

    return (
      <table className="table table-condensed">
        <tbody>
          <tr>
            <td><strong>Total TTC</strong></td>
            <td className="text-right"><strong>{ (order.total / 100).formatMoney(2, window.AppData.currencySymbol) }</strong></td>
          </tr>
          <tr>
            <td><strong>Dont TVA</strong></td>
            <td className="text-right"><strong>{ (order.taxTotal / 100).formatMoney(2, window.AppData.currencySymbol) }</strong></td>
          </tr>
        </tbody>
      </table>
    )
  }
}

export default withTranslation()(OrderTotal)
