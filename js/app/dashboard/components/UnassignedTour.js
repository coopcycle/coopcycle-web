import React, {useState, useEffect} from 'react'
import { connect } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"
import _ from 'lodash'
import Popconfirm from 'antd/lib/popconfirm'

import Task from './Task'
import { removeTaskFromTour, modifyTour, deleteTour } from '../redux/actions'
import classNames from 'classnames'

const UnassignedTour = ({ tour, tasks, removeTaskFromTour, isDropDisabled, modifyTour, deleteTour }) => {


  const { t } = useTranslation(),
        collapseId = `tour-panel-${tour['@id'].replaceAll('/', '-')}`,
        [toggleInputForName, setToggleInputForName] = useState(false),
        [tourName, setTourName] = useState(tour.name),
        onEditSubmitted = async (e) => {
          e.preventDefault()
          $('.task__draggable').LoadingOverlay('show', {image: false})
          let _tour = Object.assign({}, tour, {name : tourName})
          await modifyTour(_tour, tasks)
          setToggleInputForName(false)
          $('.task__draggable').LoadingOverlay('hide')
        },
        onEditCancelled = (e) => {
          e.preventDefault()
          setToggleInputForName(false)
        },
        renderEditNameForm = () => {
          return (
            <form onSubmit={(e) => onEditSubmitted(e)} className="d-flex flex-grow-1">
              <input autoFocus type="text" name="group-name" className="mx-2 flex-grow-1 group__editable"
                value={tourName}
                onChange={ (e) => setTourName(e.target.value) }
                onKeyDown={e => e.key === 'Escape' ? onEditCancelled(e) : null }>
              </input>
              <div className="flex-grow-0">
                <a role="button" href="#" className="text-reset mr-3"
                  onClick={ e =>  onEditSubmitted(e)}
                  title={t("CHANGE_TOUR_NAME")}
                  >
                  <i className="fa fa-check"></i>
                </a>
                <a role="button" href="#" className="text-reset flex-grow-0"
                  onClick={ e => onEditCancelled(e) }>
                  <i className="fa fa-times"></i>
                </a>
              </div>
            </form>
          )
        },
        onConfirmDelete = async (e) => {
          e.preventDefault()
          $('.task__draggable').LoadingOverlay('show', {image: false})
          await deleteTour(tour, tasks)
          $('.task__draggable').LoadingOverlay('hide')
        }
      
    useEffect(() => {
      if(!tour.items || !tour.items.length) {
        $(`#${collapseId}`).collapse('show')
      }
    }, [tour.items])        


  return (
    <div className="panel panel-default nomargin task__draggable">
      <div className="panel-heading" role="tab">
        <h4 className="panel-title d-flex align-items-center">
          <i className="fa fa-repeat flex-grow-0"></i>
            { 
              !toggleInputForName &&
              <>
                <a role="button" data-toggle="collapse" href={ `#${collapseId}` } className="ml-2 flex-grow-1 text-truncate">
                  { tourName } <span className="badge">{ tasks.length }</span>
                </a>
                <div className="d-flex flex-grow-0">
                      <a role="button" href="#" className="text-reset mr-2"
                        onClick={ () => setToggleInputForName(true) }>
                        <i className="fa fa-pencil"></i>
                      </a>
                      <Popconfirm
                      placement="left"
                      title={ t('ADMIN_DASHBOARD_DELETE_TOUR_CONFIRM') }
                      onConfirm={ onConfirmDelete }
                      okText={ t('CROPPIE_CONFIRM') }
                      cancelText={ t('ADMIN_DASHBOARD_CANCEL') }
                      >
                      <a role="button" href="#" className="text-reset"
                        onClick={ e => e.preventDefault() }>
                        <i className="fa fa-trash"></i>
                      </a>
                  </Popconfirm>
                </div>
            </>
            }
            { 
              toggleInputForName && renderEditNameForm() 
            }
        </h4>
      </div>
      <div id={ `${collapseId}` } className="panel-collapse collapse" role="tabpanel">
        <Droppable isDropDisabled={isDropDisabled} droppableId={ `unassigned_tour:${tour['@id']}` }>
            {(provided) => (
              <div
              className={ classNames({
                'taskList__tasks': true,
                'list-group': true,
                'm-0': true,
                'list-group-padded': true,
                'nomargin': true,
                'taskList__tasks--empty': !tasks.length
              }) }
              ref={ provided.innerRef } { ...provided.droppableProps }>
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
                            onRemove={ (taskToRemove) => removeTaskFromTour(tour, taskToRemove) }
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

function mapDispatchToProps(dispatch) {

  return {
    removeTaskFromTour: (tour, task) => dispatch(removeTaskFromTour(tour, task)),
    modifyTour: (tour, tasks) => dispatch(modifyTour(tour, tasks)),
    deleteTour: (tour, tasks) => dispatch(deleteTour(tour, tasks)),
  }
}
  
export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(UnassignedTour))
