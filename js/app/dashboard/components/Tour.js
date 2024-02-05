import React, {useState} from 'react'
import { connect } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"
import _ from 'lodash'
import Popconfirm from 'antd/lib/popconfirm'

import Task from './Task'
import { removeTaskFromTour, modifyTour, deleteTour, unassignTasks, toggleTourPanelExpanded } from '../redux/actions'
import { isTourAssigned, tourIsAssignedTo } from '../../../shared/src/logistics/redux/selectors'
import classNames from 'classnames'
import { useContextMenu } from 'react-contexify'
import { getDroppableListStyle } from '../utils'


const Tour = ({ tour, removeTaskFromTour, unassignTasks, isDroppable, expandedTourPanelsIds, modifyTour, deleteTour, toggleTourPanelExpanded }) => {


  const { t } = useTranslation(),
        { show } = useContextMenu({
          id: 'dashboard',
        }),
        [toggleInputForName, setToggleInputForName] = useState(false),
        [tourName, setTourName] = useState(tour.name),
        onEditSubmitted = async (e) => {
          e.preventDefault()
          $('.task__draggable').LoadingOverlay('show', {image: false})
          let _tour = Object.assign({}, tour, {name : tourName})
          await modifyTour(_tour, tour.items)
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
          await deleteTour(tour, tour.items)
          $('.task__draggable').LoadingOverlay('hide')
        },
        isExpanded = expandedTourPanelsIds.includes(tour['@id'])

  return (
    <div className="panel panel-default panel--tour nomargin task__draggable" onContextMenu={(e) => show(e, {
      props: { tour }
    })}>
      <div className="panel-heading" role="tab">
        <h4 className="panel-title d-flex align-items-center">
          <i className="fa fa-repeat flex-grow-0"></i>
            {
              !toggleInputForName &&
              <>
                <a role="button" onClick={() => toggleTourPanelExpanded(tour['@id'])} className="ml-2 flex-grow-1 text-truncate">
                  { tourName } <span className="badge">{ tour.items.length }</span>
                </a>
                <div className="d-flex flex-grow-0">
                      <a role="button" href="#" className="text-reset mr-2"
                        onClick={ () => setToggleInputForName(true) }>
                        <i className="fa fa-pencil"></i>
                      </a>
                      { isTourAssigned(tour) ?  (
                          <a
                            onClick={() => unassignTasks(tourIsAssignedTo(tour), tour.items)}
                            title={ t('ADMIN_DASHBOARD_UNASSIGN_TOUR', { name: tour.name }) }
                            className="text-reset mr-2"
                          >
                            <i className="fa fa-times"></i>
                          </a>)
                          : <Popconfirm
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
                     }
                </div>
            </>
            }
            {
              toggleInputForName && renderEditNameForm()
            }
        </h4>
      </div>
      <div className={classNames({"panel-collapse": true,  "collapse": true, "in": isExpanded})} role="tabpanel">
        <Droppable 
            isDropDisabled={!isDroppable}
            droppableId={ `tour:${tour['@id']}` }
          >
            {(provided, snapshot) => (
              <div
              className={ classNames({
                'taskList__tasks': true,
                'list-group': true,
                'm-0': true,
                'p-0': true,
                'nomargin': true,
                'taskList__tasks--empty': !tour.items.length
              }) }
              style={getDroppableListStyle(snapshot.isDraggingOver)}
              ref={ provided.innerRef } { ...provided.droppableProps }>
                { _.map(tour.items, (task, index) => {
                  return (
                    <Draggable key={ `task-${task.id}` } draggableId={ `${task['@id']}` } index={ index }>
                      {(provided) => (
                        <div
                          ref={ provided.innerRef }
                          { ...provided.draggableProps }
                          { ...provided.dragHandleProps }
                        >
                          <Task
                            key={ task['@id'] }
                            task={ task }
                            onRemove={ (taskToRemove) => removeTaskFromTour(tour, taskToRemove, tourIsAssignedTo(tour)) }
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
        isDroppable: state.logistics.ui.areToursDroppable,
        expandedTourPanelsIds: state.logistics.ui.expandedTourPanelsIds,
    }
  }

function mapDispatchToProps(dispatch) {

  return {
    removeTaskFromTour: (tour, task, username) => dispatch(removeTaskFromTour(tour, task, username)),
    modifyTour: (tour, tasks) => dispatch(modifyTour(tour, tasks)),
    deleteTour: (tour, tasks) => dispatch(deleteTour(tour, tasks)),
    unassignTasks: (tour, tasks) => dispatch(unassignTasks(tour, tasks)),
    toggleTourPanelExpanded: (tourId) => dispatch(toggleTourPanelExpanded(tourId))
  }
}
  
export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Tour))
