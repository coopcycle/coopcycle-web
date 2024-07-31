import React from 'react'
import { useTranslation } from 'react-i18next'

export default ({ edenred, card }) => {

  const { t } = useTranslation()

  return (
    <div>
      <div className="alert alert-info mb-0">
        <i className="fa fa-info-circle mr-2"></i>
        <span>{ t('MEAL_VOUCHER_DISCLAIMER') }</span>
      </div>
      <table className="table">
        <tbody>
          <tr style={{ color: 'lightseagreen' }}>
            <th>{ t('PAYMENT_FORM_ELIGIBLE_AMOUNT_EDENRED') }</th>
            <td className="text-right text-monospace">{ (edenred / 100).formatMoney() }</td>
          </tr>
          <tr>
            <th>{ t('PAYMENT_FORM_CARD_COMPLEMENT') }</th>
            <td className="text-right text-monospace">{ (card / 100).formatMoney() }</td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}
