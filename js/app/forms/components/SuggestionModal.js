import React from 'react'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import { Trans, useTranslation } from 'react-i18next'
import { Timeline } from 'antd'
import {
  rejectSuggestions,
  acceptSuggestions,
  selectSuggestions,
  selectSuggestedGain,
  selectSuggestedTasks,
  selectIsSuggestionsModalOpen,
} from '../redux/suggestionsSlice'

const SuggestionsModal = ({
  isOpen,
  onClickYes,
  onClickNo,
  tasks,
  suggestedTasks,
  suggestedGain,
  suggestions,
}) => {
  const { t } = useTranslation()

  const currentTimelineItems = tasks.map((task, index) => ({
    key: `current-order-${index}`,
    children: task.address?.streetAddress,
  }))

  const suggestedTimelineItems = suggestedTasks.map((task, index) => ({
    key: `suggested-order-${index}`,
    children: task.address?.streetAddress,
  }))

  return (
    <Modal
      isOpen={isOpen}
      shouldCloseOnOverlayClick={false}
      className="ReactModal__Content--optimization-suggestions"
      overlayClassName="ReactModal__Overlay--optimization-suggestions">
      <p>{t('DELIVERY_OPTIMIZATION_SUGGESTION_TITLE')}</p>
      <p>
        <Trans
          i18nKey="DELIVERY_OPTIMIZATION_SUGGESTION_GAIN"
          values={{
            distance: (suggestedGain.amount / 1000).toFixed(2) + ' Km',
          }}
          components={{ b: <strong /> }}
        />
      </p>
      <div className="d-flex my-4 border-bottom">
        <div className="w-50 mr-3">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_CURRENT')}
          </strong>
          <Timeline items={currentTimelineItems} />
        </div>
        <div className="w-50">
          <strong className="mb-4 d-block">
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_SUGGESTED')}
          </strong>
          <Timeline items={suggestedTimelineItems} />
        </div>
      </div>
      <div className="text-center">
        <div className="mb-2">
          {t('DELIVERY_OPTIMIZATION_SUGGESTION_CONFIRM')}
        </div>
        <div className="d-flex align-items-center justify-content-center">
          <button className="btn btn-default" type="button" onClick={onClickNo}>
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_NO')}
          </button>
          <button
            className="btn btn-primary ml-4"
            type="button"
            onClick={() => onClickYes(suggestions)}>
            {t('DELIVERY_OPTIMIZATION_SUGGESTION_YES')}
          </button>
        </div>
      </div>
    </Modal>
  )
}

function mapStateToProps(state) {
  const suggestions = selectSuggestions(state)
  const suggestedGain = selectSuggestedGain(state)
  const suggestedTasks = selectSuggestedTasks(state)

  return {
    tasks: state.tasks,
    suggestions,
    suggestedTasks,
    suggestedGain,
    isOpen: selectIsSuggestionsModalOpen(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    onClickYes: suggestions => dispatch(acceptSuggestions(suggestions)),
    onClickNo: () => dispatch(rejectSuggestions()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(SuggestionsModal)
