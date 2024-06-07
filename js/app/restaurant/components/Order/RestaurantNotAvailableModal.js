import React from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { useDispatch, useSelector } from 'react-redux'
import {
  closeRestaurantNotAvailableModal,
} from '../../redux/actions'
import {
  selectIsRestaurantNotAvailableModalOpen,
} from '../../redux/selectors'

export default function RestaurantNotAvailableModal() {
  const isModalOpen = useSelector(
    selectIsRestaurantNotAvailableModalOpen)

  const { t } = useTranslation()

  const dispatch = useDispatch()

  return (
    <Modal
      isOpen={ isModalOpen }
      contentLabel={ t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
      className="ReactModal__Content--restaurant">
      <div data-testid="cart.timeRangeChangedModal">
        <div className="text-center">
          <p>
            { t('CART_RESTAURANT_NOT_AVAILABLE_MODAL_TEXT_LINE_1') }
          </p>
        </div>
        <div className="ReactModal__Restaurant__button">
          <button
            type="button"
            className="btn btn-primary"
            onClick={ () => {
              dispatch(closeRestaurantNotAvailableModal())
              window.location.href = window.Routing.generate('restaurants')
            } }>
            { t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANTS') }
          </button>
        </div>
      </div>
    </Modal>
  )
}
