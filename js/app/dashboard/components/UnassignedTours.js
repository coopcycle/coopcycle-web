import React, { useEffect } from 'react'
import _ from 'lodash'

import { Draggable, Droppable } from "@hello-pangea/dnd"
import { connect, useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'

import Tour from './Tour'
import { appendToUnassignedTours, deleteGroup, editGroup, openCreateTourModal } from '../redux/actions'
import { selectUnassignedTours } from '../../../shared/src/logistics/redux/selectors'
import TaskGroup from './TaskGroup'
import { selectGroups, selectIsTourDragging, selectOrderOfUnassignedToursAndGroups } from '../redux/selectors'
import classNames from 'classnames'
import { getDroppableListStyle } from '../utils'


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
  const items = [...groups, ...tours]
  const dispatch = useDispatch()
  const isTourDragging = useSelector(selectIsTourDragging)
  const unassignedToursOrGroupsOrderIds = useSelector(selectOrderOfUnassignedToursAndGroups)

  useEffect(() => {
    const itemIds = [...groups.map(t => t['@id']), ...tours.map(t => t['@id'])]

    const itemsToAppendIds = _.filter(itemIds, t => !unassignedToursOrGroupsOrderIds.includes(t))

    let itemsToRemoveIds = _.filter(unassignedToursOrGroupsOrderIds, taskId => !itemIds.includes(taskId))

    if (itemsToAppendIds.length > 0 || itemsToRemoveIds.length > 0) {
      dispatch(appendToUnassignedTours({itemsToAppendIds, itemsToRemoveIds}))
    }

  }, [tours, groups]);

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
          key={items.length} // assign a mutable key to trigger a re-render when inserting a nested droppable (for example : a new tour)
          isDropDisabled={!isTourDragging}
          >
          {(provided, snapshot) => (
            <div ref={ provided.innerRef } { ...provided.droppableProps }>
               <div
                  className={ classNames({
                    'taskList__tasks': true,
                    'list-group': true,
                    'm-0': true,
                  }) }
                  style={getDroppableListStyle(snapshot.isDraggingOver)}
                >
              {_.map(unassignedToursOrGroupsOrderIds, (itemId, index) => {
                const item = items.find(i => i['@id'] === itemId)
                if (item && itemId.startsWith('/api/task_groups')) {
                  return (
                    <Draggable key={ `group-${item.id}` } draggableId={ `group:${item.id}` } index={ index }>
                      {(provided) => (
                        <div
                          ref={ provided.innerRef }
                          { ...provided.draggableProps }
                          { ...provided.dragHandleProps }
                        >
                          <TaskGroup
                            key={ item.id }
                            group={ item }
                            tasks={ item.tasks }
                            onConfirmDelete={ () => dispatch(deleteGroup(item)) }
                            onEdit={ (data) => dispatch(editGroup(data)) } />
                        </div>
                      )}
                    </Draggable>
                  )
                } else if (item && itemId.startsWith('/api/tours')) {
                  return <Tour key={ item['@id'] } tour={ item } draggableIndex={ index } />
                }
              })}
              { provided.placeholder }
              </div>
            </div>
          )}
        </Droppable>
      </div>
    </div>
  )
}