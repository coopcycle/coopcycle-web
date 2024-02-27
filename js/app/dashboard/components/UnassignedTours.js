import React from 'react'
import _ from 'lodash'

import { Draggable, Droppable } from "@hello-pangea/dnd"
import { connect, useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'

import Tour from './Tour'
import { deleteGroup, editGroup, openCreateTourModal } from '../redux/actions'
import { selectUnassignedTours } from '../../../shared/src/logistics/redux/selectors'
import TaskGroup from './TaskGroup'
import { selectGroups } from '../redux/selectors'


const Buttons = connect(
  () => ({}),
  (dispatch) => ({
    openCreateTourModal: () => dispatch(openCreateTourModal()),
  })
)(({ openCreateTourModal }) => {
  return (
    <React.Fragment>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        openCreateTourModal()
      }}>
        <i className="fa fa-plus"></i>
      </a>
    </React.Fragment>
  )
})


export const UnassignedTours = () => {

  const { t } = useTranslation()
  const groups = useSelector(selectGroups)
  const tours = useSelector(selectUnassignedTours)
  const dispatch = useDispatch()

  return (
    <div className="dashboard__panel">
      <h4 className="d-flex justify-content-between">
        <span>{ t('DASHBOARD_UNASSIGNED_TOURS') }</span>
        <span>
          <Buttons />
        </span>
      </h4>
      <div className="dashboard__panel__scroll">
        <Droppable 
          droppableId="unassigned_tours" 
          key={tours.length} // assign a mutable key to trigger a re-render when inserting a nested droppable (for example : a new tour)
          >
          {(provided) => (
            <div className="list-group nomargin" ref={ provided.innerRef } { ...provided.droppableProps }>
              { _.map(groups, (group, index) => {
                return (
                  <Draggable key={ `group-${group.id}` } draggableId={ `group:${group.id}` } index={ index }>
                    {(provided) => (
                      <div
                        ref={ provided.innerRef }
                        { ...provided.draggableProps }
                        { ...provided.dragHandleProps }
                      >
                        <TaskGroup
                          key={ group.id }
                          group={ group }
                          tasks={ group.tasks }
                          onConfirmDelete={ () => dispatch(deleteGroup(group)) }
                          onEdit={ (data) => dispatch(editGroup(data)) } />
                      </div>
                    )}
                  </Draggable>
                )
              })}
              { _.map(tours, (tour, index) => {
                return (
                  <Draggable key={ `tour-${tour['@id']}` } draggableId={ `tour:${tour['@id']}` } index={ groups.length + index }>
                    {(provided) => (
                      <div
                        ref={ provided.innerRef }
                        { ...provided.draggableProps }
                        { ...provided.dragHandleProps }
                      >
                        <Tour
                          key={ tour['@id'] }
                          tour={ tour }
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