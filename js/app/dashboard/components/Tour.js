import React from 'react'
import { withTranslation, useTranslation } from 'react-i18next'
import Task from './Task'

const Tour = ({ tour, tasks, username = null, unassignTasks = null }) => {

  const { t } = useTranslation()

  const collapseId = `tour-panel-${tour['@id'].replaceAll('/', '-')}`

  return (
    <div className="panel panel-default nomargin task__draggable">
      <div className="panel-heading" role="tab">
        <h4 className="panel-title d-flex align-items-center">
          <i className="fa fa-repeat flex-grow-0"></i>
            <a role="button" data-toggle="collapse" href={ `#${collapseId}` } className="ml-2 flex-grow-1 text-truncate">
              { tour.name } <span className="badge">{ tasks.length }</span>
            </a>
            { username && (
              <a 
                onClick={() => unassignTasks(username, tasks)}
                title={ t('ADMIN_DASHBOARD_UNASSIGN_TOUR', { name: tour.name }) }
              >
                <i className="fa fa-times"></i>
              </a>
            )}
        </h4>
      </div>
      <div id={ `${collapseId}` } className="panel-collapse collapse" role="tabpanel">
        <ul className="list-group list-group-padded">
          { tasks.map(task => {
            return (
              <Task
                key={ task['@id'] }
                task={ task }
                assigned={ false }
              />
            )
          })}
        </ul>
      </div>
    </div>
  )
}

export default withTranslation()(Tour)
