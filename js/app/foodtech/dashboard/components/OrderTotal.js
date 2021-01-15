import React from 'react'
import { withTranslation } from 'react-i18next'

export default withTranslation()(({ order, t }) => {

  return (
    <table className="table table-condensed">
      <tbody>
        <tr>
          <td><strong>{ t('CART_TOTAL_PRODUCTS') }</strong></td>
          <td className="text-right"><strong>{ (order.itemsTotal / 100).formatMoney() }</strong></td>
        </tr>
        <tr>
          <td><strong>{ t('CART_TOTAL') }</strong></td>
          <td className="text-right"><strong>{ (order.total / 100).formatMoney() }</strong></td>
        </tr>
      </tbody>
    </table>
  )
})
