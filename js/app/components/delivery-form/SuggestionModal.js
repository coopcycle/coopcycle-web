import React, { useCallback, useMemo } from 'react'
import Modal from 'react-modal'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Timeline } from 'antd'
import {
  rejectSuggestions,
  acceptSuggestions,
  selectSuggestedGain,
  selectSuggestedOrder,
  selectShowSuggestions,
} from './redux/suggestionsSlice'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'

import './SuggestionModal.scss'

const SuggestionsModal = () => {
  const { values, setFieldValue, setSubmitting, submitForm } =
    useDeliveryFormFormikContext()

  const tasks = useMemo(() => {
    return values.tasks || []
  }, [values.tasks])

  const suggestedGain = useSelector(selectSuggestedGain)
  const suggestedOrder = useSelector(selectSuggestedOrder)

  const suggestedTasks = useMemo(() => {
    const suggestedTasks = []
    suggestedOrder.forEach((oldIndex, newIndex) => {
      suggestedTasks.splice(newIndex, 0, tasks[oldIndex])
    })

    return suggestedTasks
  }, [suggestedOrder, tasks])

  const showSuggestions = useSelector(selectShowSuggestions)

  const isOpen = useMemo(() => {
    return suggestedTasks.length > 0 && showSuggestions
  }, [suggestedTasks, showSuggestions])

  const { t } = useTranslation()

  const dispatch = useDispatch()

  const accept = useCallback(() => {
    dispatch(acceptSuggestions(suggestedOrder))

    setSubmitting(false)

    const reOrderedTasks = []

    suggestedOrder.forEach(oldIndex => {
      reOrderedTasks.push(tasks[oldIndex])
    })

    setFieldValue('tasks', reOrderedTasks)

    submitForm()
  }, [
    dispatch,
    setFieldValue,
    suggestedOrder,
    tasks,
    setSubmitting,
    submitForm,
  ])

  const reject = useCallback(() => {
    dispatch(rejectSuggestions(suggestedOrder))

    setSubmitting(false)
    submitForm()
  }, [dispatch, suggestedOrder, setSubmitting, submitForm])

  return (
    <Modal
      isOpen={isOpen}
      shouldCloseOnOverlayClick={false}
      className="ReactModal__Content--optimization-suggestions"
      overlayClassName="ReactModal__Overlay--zIndex-1001">
      <p>{t('DELIVERY_OPTIMIZATION_SUGGESTION_TITLE')}</p>
      <p>
        {t('DELIVERY_OPTIMIZATION_SUGGESTION_GAIN', {
          distance: (suggestedGain.amount / 1000).toFixed(2) + ' Km',
        })}
      </p>
      <div className="d-flex my-4 border-bottom">
        <div className="w-50 mr-3">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_CURRENT')}
          </strong>
          <Timeline>
            {tasks.map((task, index) => (
              <Timeline.Item key={`current-order-${index}`}>
                {task.address?.streetAddress}
              </Timeline.Item>
            ))}
          </Timeline>
        </div>
        <div className="w-50">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_SUGGESTED')}
          </strong>
          <Timeline>
            {suggestedTasks.map((task, index) => (
              <Timeline.Item key={`suggested-order-${index}`}>
                {task.address?.streetAddress}
              </Timeline.Item>
            ))}
          </Timeline>
        </div>
      </div>
      <div className="text-center">
        <div className="mb-2">
          {t('DELIVERY_OPTIMIZATION_SUGGESTION_CONFIRM')}
        </div>
        <div className="d-flex align-items-center justify-content-center">
          <button
            className="btn btn-default"
            type="button"
            onClick={() => reject()}>
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_NO')}
          </button>
          <button
            className="btn btn-primary ml-4"
            type="button"
            onClick={() => accept()}>
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_YES')}
          </button>
        </div>
      </div>
    </Modal>
  )
}

export default SuggestionsModal
