import React from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'

export default function TimeRangeChangedModal({ isModalOpen, onClick }) {
  const { t } = useTranslation()

  return (
    <Modal
      isOpen={ isModalOpen }
      contentLabel={ t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
      className="ReactModal__Content--restaurant">
      <div data-testid="cart.timeRangeChangedModal">
        <div className="text-center">
          <p>
            { t('CART_TIME_RANGE_CHANGED_MODAL_TEXT_LINE_1') }
          </p>
        </div>
        <div className="ReactModal__Restaurant__button">
          <button
            type="button"
            className="btn btn-primary"
            onClick={ onClick }>
            { t('CART_CHANGE_TIME_MODAL_LABEL') }
          </button>
        </div>
      </div>
    </Modal>
  )
}
