import React from 'react'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Timeline } from 'antd'

const SuggestionsModal = ({ isOpen, onClickYes, onClickNo, tasks, suggestedTasks, suggestedGain }) => {

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
      <div>
        <div className="mb-2">{ t('DELIVERY_OPTIMIZATION_SUGGESTION_CONFIRM') }</div>
        <div className="d-flex justify-content-between align-items-center">
          <button className="btn btn-default" type="button" onClick={ onClickNo }>
            { t('DELIVERY_OPTIMIZATION_SUGGESTION_NO') }
          </button>
          <button className="btn btn-primary mr-1" type="button" onClick={ onClickYes }>
            { t('DELIVERY_OPTIMIZATION_SUGGESTION_YES') }
          </button>
        </div>
      </div>
    </Modal>
  )
}

function mapStateToProps(state) {

  const suggestedOrder = state.suggestions.length > 0 ? state.suggestions[0].order : []
  const suggestedGain = state.suggestions.length > 0 ? state.suggestions[0].gain : {}

  const suggestedTasks = []
  suggestedOrder.forEach((oldIndex, newIndex) => {
    suggestedTasks.splice(newIndex, 0, state.tasks[oldIndex])
  })

  return {
    tasks: state.tasks,
    suggestedTasks,
    suggestedGain,
    isOpen: suggestedTasks.length > 0 && state.showSuggestions,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    onClickYes: () => dispatch({ type: 'ACCEPT_SUGGESTIONS' }),
    onClickNo: () => dispatch({ type: 'REJECT_SUGGESTIONS' }),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(SuggestionsModal)
