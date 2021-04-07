import React from 'react'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'

const HeaderText = ({ task }) => {

  const { t } = useTranslation()

  if (!!task && Object.prototype.hasOwnProperty.call(task, '@id')) {

    return (
      <span>
        { (task.orgName && !_.isEmpty(task.orgName)) && (
        <span>
          <span>{ task.orgName }</span>
          <span className="mx-2">›</span>
        </span>
        ) }
        <span>{ t('ADMIN_DASHBOARD_TASK_TITLE', { id: task.id }) }</span>
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
