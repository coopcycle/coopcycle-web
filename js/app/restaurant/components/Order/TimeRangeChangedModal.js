import React from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { useDispatch, useSelector } from 'react-redux'
import {
  closeTimeRangeChangedModal,
  setDateModalOpen,
} from '../../redux/actions'
import { selectIsTimeRangeChangedModalOpen } from '../../redux/selectors'

export default function TimeRangeChangedModal() {
  const isTimeRangeChangedModalOpen = useSelector(
    selectIsTimeRangeChangedModalOpen)

  const { t } = useTranslation()

  const dispatch = useDispatch()

  return (
    <Modal
      isOpen={ isTimeRangeChangedModalOpen }
      contentLabel={ t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
      className="ReactModal__Content--restaurant">
      <div>
        <div className="text-center">
          <p>
            { t('CART_TIME_RANGE_CHANGED_MODAL_TEXT_LINE_1') }
          </p>
        </div>
        <div className="ReactModal__Restaurant__button">
          <button
            type="button"
            className="btn btn-primary"
            onClick={ () => {
              dispatch(closeTimeRangeChangedModal())
              dispatch(setDateModalOpen(true))
            } }>
            { t('CART_CHANGE_TIME_MODAL_LABEL') }
          </button>
        </div>
      </div>
    </Modal>
  )
}
