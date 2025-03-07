import React from 'react'
import { useSelector } from 'react-redux'
import { selectSelectedTasks } from '../redux/selectors'
import ReportIncidentModalContent from '../../components/ReportIncidentModalContent'

function TaskReportIncidentModalContent() {
  const task = useSelector(selectSelectedTasks)[0]

  return <ReportIncidentModalContent task={task} />
}

export default TaskReportIncidentModalContent
