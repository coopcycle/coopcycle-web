import React from 'react'
import { connect } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"
import _ from 'lodash'
import Task from './Task'

const UnassignedTour = ({ tour, tasks, username = null, unassignTasks = null, isDropDisabled }) => {

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
        <Droppable isDropDisabled={isDropDisabled} droppableId={ `unassigned_tour:${tour['@id'].replace('/api/tours/', '')}` }>
            {(provided) => (
              <div className="list-group list-group-padded nomargin taskList__tasks m-0" ref={ provided.innerRef } { ...provided.droppableProps }>
                { _.map(tasks, (task, index) => {
                  return (
                    <Draggable key={ `task-${task.id}` } draggableId={ `task:${task.id}` } index={ index }>
                      {(provided) => (
                        <div
                          ref={ provided.innerRef }
                          { ...provided.draggableProps }
                          { ...provided.dragHandleProps }
                        >
                          <Task
                            key={ task['@id'] }
                            task={ task }
                            assigned={ false }
                            />
                        </div>
                      )}
                    </Draggable>
                  )
                })}
                { provided.placeholder }
              </div>
            )}
          </Droppable>
      </div>
    </div>
  )
}

function mapStateToProps (state) {

    return {
        isDropDisabled: state.logistics.ui.unassignedTourTasksDroppableDisabled,
    }
  }
  
export default connect(mapStateToProps)(withTranslation()(UnassignedTour))
