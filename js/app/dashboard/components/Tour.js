import React, {useState} from 'react'
import {useDispatch, useSelector } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import { Draggable, Droppable } from "@hello-pangea/dnd"
import _ from 'lodash'
import Popconfirm from 'antd/lib/popconfirm'

import Task from './Task'
import { removeTasksFromTour, modifyTour, deleteTour, unassignTasks, toggleTourPanelExpanded } from '../redux/actions'
import { isTourAssigned, tourIsAssignedTo } from '../../../shared/src/logistics/redux/selectors'
import classNames from 'classnames'
import { getDroppableListStyle } from '../utils'
import { selectIsTourDragging, selectExpandedTourPanelsIds, selectLoadingTourPanelsIds } from '../redux/selectors'

const RenderEditNameForm = ({children, tour, isLoading}) => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const [tourName, setTourName] = useState(tour.name)
  const [toggleInputForName, setToggleInputForName] = useState(false)

  const onEditSubmitted = async (e) => {
    e.preventDefault()
    let _tour = Object.assign({}, tour, {name : tourName})
    dispatch(modifyTour(_tour, tour.items))
    setToggleInputForName(false)
  }
  const onEditCancelled = (e) => {
      e.preventDefault()
      setToggleInputForName(false)
    }
  const onConfirmDelete = async (e) => {
        e.preventDefault()
        dispatch(deleteTour(tour, tour.items))
      }

  return (<>{toggleInputForName ?
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
    </form> :
    <>
      { children }
      { isLoading ?
        <span className="loader loader--dark"></span>
        : <>
          <a role="button" href="#" className="text-reset mr-2" onClick={ () => setToggleInputForName(true) }>
              <i className="fa fa-pencil"></i>
          </a>
          { isTourAssigned(tour) ?
            <a
              onClick={() => dispatch(unassignTasks(tourIsAssignedTo(tour), tour.items))}
              title={ t('ADMIN_DASHBOARD_UNASSIGN_TOUR', { name: tour.name }) }
              className="text-reset mr-2"
            >
              <i className="fa fa-times"></i>
            </a>
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
        </>
      }
    </>
  }</>)
}


const Tour = ({ tour, draggableIndex }) => {

  const isTourDragging = useSelector(selectIsTourDragging)
  const expandedTourPanelsIds = useSelector(selectExpandedTourPanelsIds)
  const isExpanded = expandedTourPanelsIds.includes(tour['@id'])

  const loadingTourIds = useSelector(selectLoadingTourPanelsIds)
  const isLoading = loadingTourIds.includes(tour['@id'])

  const dispatch = useDispatch()

  return (
    <Draggable key={ `tour:${tour['@id']}` } draggableId={ `tour:${tour['@id']}` } index={ draggableIndex }>
      {(provided) => (
        <div ref={ provided.innerRef } { ...provided.draggableProps } { ...provided.dragHandleProps }>
          <div
            className="panel panel-default panel--tour nomargin task__draggable"
            style={{ opacity: isLoading ? 0.7 : 1, pointerEvents: isLoading ? 'none' : 'initial' }}
          >
            <div className="panel-heading" role="tab">
              <h4 className="panel-title d-flex align-items-center">
                <i className="fa fa-repeat flex-grow-0"></i>
                  <RenderEditNameForm tour={tour} isLoading={isLoading}>
                    <a role="button" onClick={() => dispatch(toggleTourPanelExpanded(tour['@id']))} className="ml-2 flex-grow-1 text-truncate">
                      { tour.name } <span className="badge">{ tour.items.length }</span>
                    </a>
                    <i className="fa fa-arrows mr-2"></i>
                  </RenderEditNameForm>
              </h4>
            </div>
            <div className={classNames({"panel-collapse": true,  "collapse": true, "in": isExpanded})} role="tabpanel">
              <Droppable
                  isDropDisabled={isTourDragging || isLoading}
                  droppableId={ `tour:${tour['@id']}` }
                >
                  {(provided, snapshot) => (
                    <div ref={ provided.innerRef } { ...provided.droppableProps }>
                      <div
                      className={ classNames({
                        'taskList__tasks': true,
                        'list-group': true,
                        'm-0': true,
                        'p-0': true,
                        'nomargin': true,
                      }) }
                      style={getDroppableListStyle(snapshot.isDraggingOver)}
                      >
                        { _.map(tour.items, (task, index) =>
                          <Task
                            key={ task['@id'] }
                            task={ task }
                            draggableIndex={ index }
                            onRemove={ (taskToRemove) => dispatch(removeTasksFromTour(tour, taskToRemove, tourIsAssignedTo(tour)))}
                          />
                        )}
                        { provided.placeholder }
                      </div>
                    </div>
                  )}
                </Droppable>
            </div>
          </div>
        </div>
      )}
    </Draggable>
  )
}

export default withTranslation()(Tour)
