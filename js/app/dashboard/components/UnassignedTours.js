import React, { useEffect } from 'react'
import _ from 'lodash'

import { Draggable, Droppable } from "@hello-pangea/dnd"
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'

import Tour from './Tour'
import { appendToUnassignedTours, deleteGroup, editGroup, openCreateTourModal, setMapFilterValue } from '../redux/actions'
import { selectUnassignedTours } from '../../../shared/src/logistics/redux/selectors'
import TaskGroup from './TaskGroup'
import { selectAreToursEnabled, selectGroups, selectIsTourDragging, selectMapFiltersSetting, selectOrderOfUnassignedToursAndGroups, selectSplitDirection } from '../redux/selectors'
import classNames from 'classnames'
import { getDroppableListStyle } from '../utils'
import { Switch, Tooltip } from 'antd'


const Buttons = () => {
  const dispatch = useDispatch()
  return (
    <React.Fragment>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        dispatch(openCreateTourModal())
      }}>
        <i className="fa fa-plus"></i>
      </a>
    </React.Fragment>
  )
}

const CollapsedUnassignedTours = ({ unassignedToursCount, splitCollapseAction }) => {
  const { t } = useTranslation()
  const splitDirection = useSelector(selectSplitDirection)

  return <>
    { splitDirection == "vertical" ?
      (<div className="dashboard__panel">
        <h4 className="dashboard__panel__header d-flex justify-content-between">
          <a onClick={() => splitCollapseAction()}>
            <span className="mr-2">{ t('DASHBOARD_UNASSIGNED_TOURS') }</span>
            <span className="mr-2">({ unassignedToursCount })</span>
            <i className="fa fa-caret-down"></i>
          </a>
        </h4>
      </div>) :
      (<div className="dashboard__panel">
          <h4 className="dashboard__panel__header d-flex justify-content-center dashboard__panel__collapsed">
            <a onClick={() => splitCollapseAction()}>
              <Tooltip placement="left" title={ t('DASHBOARD_UNASSIGNED_TOURS') }>
                <span>({ unassignedToursCount })</span><br />
                <i className="fa fa-caret-right"></i>
              </Tooltip>
            </a>
          </h4>
        </div>
      )
    }
  </>
}


export const UnassignedTours = ({ splitCollapseAction }) => {

  const { t } = useTranslation()
  const groups = useSelector(selectGroups)
  const tours = useSelector(selectUnassignedTours)
  const items = [...groups, ...tours]
  const dispatch = useDispatch()
  const isTourDragging = useSelector(selectIsTourDragging)
  const unassignedToursOrGroupsOrderIds = useSelector(selectOrderOfUnassignedToursAndGroups)
  const toursEnabled = useSelector(selectAreToursEnabled)
  const splitDirection = useSelector(selectSplitDirection)
  const mapFiltersSettings = useSelector(selectMapFiltersSetting) || {}


  useEffect(() => {
    const itemIds = [...groups.map(t => t['@id']), ...tours.map(t => t['@id'])]

    const itemsToAppendIds = _.filter(itemIds, t => !unassignedToursOrGroupsOrderIds.includes(t))

    let itemsToRemoveIds = _.filter(unassignedToursOrGroupsOrderIds, taskId => !itemIds.includes(taskId))

    if (itemsToAppendIds.length > 0 || itemsToRemoveIds.length > 0) {
      dispatch(appendToUnassignedTours({itemsToAppendIds, itemsToRemoveIds}))
    }

  }, [tours, groups]);

  if (!toursEnabled) {
    return <CollapsedUnassignedTours unassignedToursCount={tours.length} splitCollapseAction={ splitCollapseAction } />
  }

  return (
    <div className="dashboard__panel">
      <h4 className="dashboard__panel__header d-flex justify-content-between">
        <a onClick={() => splitCollapseAction()}>
          <span className="mr-2">{ t('DASHBOARD_UNASSIGNED_TOURS') }</span>
          <span className="mr-2">({ tours.length + groups.length })</span>
          <Tooltip
            placement="left"
            title={t("EXPAND_COLLAPSE")}
          >
            { splitDirection == 'vertical' ?
                <i className="fa fa-caret-up"></i>
              : <i className="fa fa-caret-left"></i>
            }
          </Tooltip>
        </a>
        <span className="pull-right">
          <Tooltip
            placement="left"
            title={t("ADMIN_DASHBOARD_HIDE_SHOW_ON_MAP")}
            className="mr-2"
          >
            <Switch
              unCheckedChildren={"0"}
              checkedChildren={"I"}
              defaultChecked={mapFiltersSettings.showUnassignedTours}
              checked={mapFiltersSettings.showUnassignedTours}
              onChange={checked => {
                dispatch(setMapFilterValue({key: "showUnassignedTours", value: checked}))
              }}
            />
          </Tooltip>
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
                    <Draggable key={ `group-${itemId}` } draggableId={ `group:${itemId}` } index={ index }>
                      {(provided) => (
                        <div
                          ref={ provided.innerRef }
                          { ...provided.draggableProps }
                          { ...provided.dragHandleProps }
                        >
                          <TaskGroup
                            key={ itemId }
                            group={ item }
                            tasks={ item.tasks }
                            onConfirmDelete={ () => dispatch(deleteGroup(item)) }
                            onEdit={ (data) => dispatch(editGroup(data)) } />
                        </div>
                      )}
                    </Draggable>
                  )
                } else if (item && itemId.startsWith('/api/tours')) {
                  return <Tour key={ item['@id'] } tourId={ itemId } draggableIndex={ index } />
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