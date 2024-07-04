import React from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { useMatomo } from '../../../hooks/useMatomo'

export default function ChangeRestaurantOnEditFulfilmentDetailsModal({
  isWarningModalOpen,
  setWarningModalOpen,
}) {
  const continueURL = window.Routing.generate('order_continue')

  const { t } = useTranslation()

  const { trackEvent } = useMatomo()

  return (
    <Modal
      isOpen={isWarningModalOpen}
      onAfterOpen={() =>
        trackEvent(
          'Checkout',
          'openModal',
          'changeRestaurantOnEditFulfilmentDetails',
        )
      }
      contentLabel={t('CART_CHANGE_RESTAURANT_MODAL_LABEL')}
      className="ReactModal__Content--restaurant">
      <div>
        <div className="text-center">
          <p>
            {t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_TEXT_LINE_1')}
            <br />
            {t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_TEXT_LINE_2')}
          </p>
        </div>
        <div className="ReactModal__Content__buttons">
          <a className="btn btn-default" href={continueURL}>
            {t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_BTN_NO')}
          </a>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => setWarningModalOpen(false)}>
            {t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_BTN_YES')}
          </button>
        </div>
      </div>
    </Modal>
  )
}
