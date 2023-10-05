import React from 'react'
import {connect} from 'react-redux'
import RescheduleTask from "./RescheduleTask";
import {selectCurrentTask} from "../redux/selectors";

const ModalContent = ({ task }) => {

  return (
    <div className="px-5 pt-5">
      <RescheduleTask task={task} />
    </div>
  )
}

function mapStateToProps(state) {

  return {
    task: selectCurrentTask(state)
  }
}

function mapDispatchToProps() {

  return { }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
