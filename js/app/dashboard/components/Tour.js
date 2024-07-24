import React, {useState} from 'react'
import {useDispatch, useSelector } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import { Draggable, Droppable } from "@hello-pangea/dnd"
import _ from 'lodash'
import Popconfirm from 'antd/lib/popconfirm'

import Task from './Task'
import { removeTasksFromTour, modifyTour, deleteTour, unassignTasks, toggleTourPanelExpanded, toggleTourPolyline } from '../redux/actions'
import { selectTourById, selectItemAssignedTo, selectTourWeight, selectTourVolumeUnits } from '../../../shared/src/logistics/redux/selectors'
import classNames from 'classnames'
import { getDroppableListStyle } from '../utils'
import { selectIsTourDragging, selectExpandedTourPanelsIds, selectLoadingTourPanelsIds, selectTourPolylinesEnabledById, selectTourIdToColorMap } from '../redux/selectors'
import ExtraInformations from './TaskCollectionDetails'
import PolylineIcon from './icons/PolylineIcon'


const RenderEditNameForm = ({children, tour, isLoading}) => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const [tourName, setTourName] = useState(tour.name)
  const [toggleInputForName, setToggleInputForName] = useState(false)
  const tourAssignedTo = useSelector((state) => selectItemAssignedTo(state, tour['@id']))

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
          { tourAssignedTo ?
            <a
              onClick={() => dispatch(unassignTasks(tourAssignedTo, [tour]))}
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


const Tour = ({ tourId, draggableIndex }) => {

  const tour = useSelector(state => selectTourById(state, tourId))

  const isTourDragging = useSelector(selectIsTourDragging)
  const expandedTourPanelsIds = useSelector(selectExpandedTourPanelsIds)
  const isExpanded = expandedTourPanelsIds.includes(tour['@id'])

  const loadingTourIds = useSelector(selectLoadingTourPanelsIds)
  const isLoading = loadingTourIds.includes(tour['@id'])

  const polylineEnabled = useSelector(selectTourPolylinesEnabledById(tourId))
  const color = useSelector(selectTourIdToColorMap).get(tourId)

  const dispatch = useDispatch()

  const weight = useSelector(state => selectTourWeight(state, tourId))
  const volumeUnits = useSelector(state => selectTourVolumeUnits(state, tourId))

  return (
    <Draggable key={ `tour:${tour['@id']}` } draggableId={ `tour:${tour['@id']}` } index={ draggableIndex }>
      {(provided) => (
        <div ref={ provided.innerRef } { ...provided.draggableProps } { ...provided.dragHandleProps }>
          <div
            className="panel panel-default panel--tour nomargin task__draggable"
            style={{ opacity: isLoading ? 0.7 : 1, pointerEvents: isLoading ? 'none' : 'initial' }}
          >
            <div className="panel-heading" role="tab" onClick={() => dispatch(toggleTourPanelExpanded(tour['@id']))}>
              <h4 className="panel-title d-flex align-items-center">
                <i className="fa fa-repeat flex-grow-0"></i>
                  <RenderEditNameForm tour={tour} isLoading={isLoading}>
                    <a role="button" className="ml-2 flex-grow-1 text-truncate">
                      { tour.name } <span className="badge" style={{backgroundColor: color}}>{ tour.items.length }</span>
                    </a>
                    <i className="fa fa-arrows cursor--grabbing mr-2"></i>
                  </RenderEditNameForm>
              </h4>
              <ExtraInformations duration={tour.duration} distance={tour.distance} weight={weight} volumeUnits={volumeUnits}/>
            </div>
            <div className={classNames("panel-collapse collapse", {"in": isExpanded})} role="tabpanel">
              { tour.items.length > 0 ?
                <div className="d-flex align-items-center mt-2 mb-2">
                  <a
                    className='tasklist__actions--icon ml-3'
                    onClick={ () => dispatch(toggleTourPolyline(tour['@id'])) }
                  >
                    <PolylineIcon fillColor={polylineEnabled ? '#EEB516' : null} />
                  </a>
                </div>
              : null }
              <Droppable
                  isDropDisabled={isTourDragging || isLoading}
                  droppableId={ `tour:${tour['@id']}` }
                >
                  {(provided, snapshot) => (
                    <div ref={ provided.innerRef } { ...provided.droppableProps }>
                      <div
                      className="taskList__tasks list-group m-0 p-0 nomargin"
                      style={getDroppableListStyle(snapshot.isDraggingOver)}
                      >
                        { _.map(tour.items, (taskId, index) =>
                          <Task
                            key={ taskId }
                            taskId={ taskId }
                            draggableIndex={ index }
                            onRemove={ (taskToRemove) => dispatch(removeTasksFromTour(tour, taskToRemove))}
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
