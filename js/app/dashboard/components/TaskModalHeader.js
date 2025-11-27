import React from 'react'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'
import { formatTaskNumber } from '../../utils/taskUtils';
import TaskStatusBadge from './TaskStatusBadge';

const HeaderText = ({ task }) => {

  const { t } = useTranslation()

  if (!!task && Object.prototype.hasOwnProperty.call(task, '@id')) {

    return (
      <span className="d-inline-flex align-items-end" data-testid="task-modal-title">
        { (task.orgName && !_.isEmpty(task.orgName)) && (
        <span>
          <span>{ task.orgName }</span>
          <span className="mx-2">›</span>
        </span>
        ) }
        <span>{ t('TASK_WITH_NUMBER', { number: formatTaskNumber(task) }) }</span>
        <TaskStatusBadge task={ task } className="ml-2" />
      </span>
    )
  }

  return (
    <span>{ t('ADMIN_DASHBOARD_TASK_TITLE_NEW') }</span>
  )
}

const TaskModalHeader = ({ task, onCloseClick }) => {

  return (
    <div className="modal-header">
      <h4 className="modal-title">
        <span>
          <HeaderText task={ task } />
        </span>
        <a href="#" className="pull-right" onClick={ onCloseClick }>
          <i className="fa fa-times" aria-hidden="true"></i>
        </a>
      </h4>
    </div>
  )
}

export default TaskModalHeader
