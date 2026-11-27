import React from 'react'
import { useTranslation } from 'react-i18next'

/**
 * What the gift card is covering, and what is left to pay by card.
 *
 * Without this the payment page shows the full order total next to a card
 * form, and a customer about to be charged 7,74 € has no way of knowing it
 * isn't 32,74 €.
 */
export default ({ giftCard, card }) => {
  const { t } = useTranslation()

  return (
    <table className="table">
      <tbody>
        <tr style={{ color: 'lightseagreen' }}>
          <th>{t('PAYMENT_FORM_GIFT_CARD_AMOUNT')}</th>
          <td className="text-right text-monospace">
            − {(giftCard / 100).formatMoney()}
          </td>
        </tr>
        {card > 0 && (
          <tr>
            <th>{t('PAYMENT_FORM_CARD_COMPLEMENT')}</th>
            <td className="text-right text-monospace">
              {(card / 100).formatMoney()}
            </td>
          </tr>
        )}
      </tbody>
    </table>
  )
}
