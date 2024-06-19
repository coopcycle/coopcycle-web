import React from 'react'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import { Timeline } from 'antd'

const SuggestionsModal = ({ isOpen, onClickYes, onClickNo, tasks, suggestedTasks }) => {

  return (
    <Modal
      isOpen={ isOpen }
      shouldCloseOnOverlayClick={ false }
      className="ReactModal__Content--optimization-suggestions"
      overlayClassName="ReactModal__Overlay--optimization-suggestions">
      <p>We have found a more optimized route for your delivery.</p>
      <div className="d-flex my-4 border-bottom">
        <div className="w-50 mr-3">
          <Timeline>
            { tasks.map((task, index) =>
              <Timeline.Item key={ `current-order-${index}` }>{ task.address?.streetAddress }</Timeline.Item>
            ) }
          </Timeline>
        </div>
        <div className="w-50">
          <Timeline>
            { suggestedTasks.map((task, index) =>
              <Timeline.Item key={ `suggested-order-${index}` }>{ task.address?.streetAddress }</Timeline.Item>
            ) }
          </Timeline>
        </div>
      </div>
      <div className="d-flex justify-content-between align-items-center">
        <span>Apply the optimized route?</span>
        <div>
          <button className="btn btn-primary mr-1" type="button" onClick={ onClickYes }>Yes</button>
          <button className="btn btn-default" type="button" onClick={ onClickNo }>No</button>
        </div>
      </div>
    </Modal>
  )
}

function mapStateToProps(state) {

  const suggestedOrder = state.suggestions.length > 0 ? state.suggestions[0].order : []

  const suggestedTasks = []
  suggestedOrder.forEach((oldIndex, newIndex) => {
    suggestedTasks.splice(newIndex, 0, state.tasks[oldIndex])
  })

  return {
    tasks: state.tasks,
    suggestedTasks,
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
