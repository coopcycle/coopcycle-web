import React, { useCallback, useMemo } from 'react'
import Modal from 'react-modal'
import { useDispatch, useSelector } from 'react-redux'
import { Trans, useTranslation } from 'react-i18next'
import {
  rejectSuggestions,
  acceptSuggestions,
  selectSuggestedGain,
  selectSuggestedOrder,
  selectShowSuggestions,
} from './redux/suggestionsSlice'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'

import './SuggestionModal.scss'
import Itinerary from '../DeliveryItinerary'
import { TaskPayload as Task } from '../../api/types'

const SuggestionsModal = () => {
  const { values, setFieldValue, setSubmitting, submitForm } =
    useDeliveryFormFormikContext()

  const tasks = useMemo((): Task[] => {
    return values.tasks || []
  }, [values.tasks])

  const suggestedGain = useSelector(selectSuggestedGain)
  const suggestedOrder = useSelector(selectSuggestedOrder)

  const suggestedTasks = useMemo((): Task[] => {
    const suggestedTasks: Task[] = []
    suggestedOrder.forEach((oldIndex: number, newIndex: number) => {
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
      <h3
        className="text-center"
        data-testid="delivery-optimization-suggestion-title">
        {t('DELIVERY_OPTIMIZATION_SUGGESTION_TITLE')}
      </h3>
      <h4 className="text-center">
        <Trans
          i18nKey="DELIVERY_OPTIMIZATION_SUGGESTION_GAIN"
          values={{
            distance: (suggestedGain.amount / 1000).toFixed(2) + ' Km',
          }}
          components={{ b: <strong /> }}
        />
      </h4>
      <div className="d-flex my-4 border-bottom">
        <div className="w-50 mr-3">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_CURRENT')}
          </strong>
          <Itinerary tasks={tasks} />
        </div>
        <div className="w-50">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_SUGGESTED')}
          </strong>
          <Itinerary tasks={suggestedTasks} />
        </div>
      </div>
      <div>
        <h4 className="text-center">
          {t('DELIVERY_OPTIMIZATION_SUGGESTION_CONFIRM')}
        </h4>
        <div className="my-3 d-flex align-items-center justify-content-center">
          <button
            data-testid="delivery-optimization-suggestion-reject"
            className="btn btn-default"
            type="button"
            onClick={() => reject()}>
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_NO')}
          </button>
          <button
            data-testid="delivery-optimization-suggestion-accept"
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
