import React from 'react'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Timeline } from 'antd'
import {
  rejectSuggestions,
  acceptSuggestions,
  selectSuggestions,
  selectSuggestedGain,
  selectSuggestedTasks,
  selectIsSuggestionsModalOpen } from '../redux/suggestionsSlice'

const SuggestionsModal = ({ isOpen, onClickYes, onClickNo, tasks, suggestedTasks, suggestedGain, suggestions }) => {

  const { t } = useTranslation()

  return (
    <Modal
      isOpen={ isOpen }
      shouldCloseOnOverlayClick={ false }
      className="ReactModal__Content--optimization-suggestions"
      overlayClassName="ReactModal__Overlay--optimization-suggestions">
      <p>{ t('DELIVERY_OPTIMIZATION_SUGGESTION_TITLE') }</p>
      <p>{ t('DELIVERY_OPTIMIZATION_SUGGESTION_GAIN', { distance: (suggestedGain.amount / 1000).toFixed(2) + ' Km' }) }</p>
      <div className="d-flex my-4 border-bottom">
        <div className="w-50 mr-3">
          <strong className="mb-4 d-block">{ t('DELIVERY_OPTIMIZATION_SUGGESTION_CURRENT') }</strong>
          <Timeline>
            { tasks.map((task, index) =>
              <Timeline.Item key={ `current-order-${index}` }>{ task.address?.streetAddress }</Timeline.Item>
            ) }
          </Timeline>
        </div>
        <div className="w-50">
          <strong className="mb-4 d-block">{ t('DELIVERY_OPTIMIZATION_SUGGESTION_SUGGESTED') }</strong>
          <Timeline>
            { suggestedTasks.map((task, index) =>
              <Timeline.Item key={ `suggested-order-${index}` }>{ task.address?.streetAddress }</Timeline.Item>
            ) }
          </Timeline>
        </div>
      </div>
      <div className="text-center">
        <div className="mb-2">{ t('DELIVERY_OPTIMIZATION_SUGGESTION_CONFIRM') }</div>
        <div className="d-flex align-items-center justify-content-center">
          <button className="btn btn-default" type="button" onClick={ onClickNo }>
            { t('DELIVERY_OPTIMIZATION_SUGGESTION_NO') }
          </button>
          <button className="btn btn-primary ml-4" type="button" onClick={ () => onClickYes(suggestions) }>
            { t('DELIVERY_OPTIMIZATION_SUGGESTION_YES') }
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
    onClickYes: (suggestions) => dispatch(acceptSuggestions(suggestions)),
    onClickNo: () => dispatch(rejectSuggestions()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(SuggestionsModal)
