import React, { useEffect, useRef } from 'react'
import { DragDropContext } from '@hello-pangea/dnd'
import Split from 'react-split'

import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'

import {
  toggleSearch,
  closeSearch,
  setToursEnabled,
  loadOrganizations
} from '../redux/actions'
import { UnassignedTasks } from './UnassignedTasks'
import { UnassignedTours } from './UnassignedTours'
import TaskLists from './TaskLists'
import SearchPanel from './SearchPanel'
import TasksContextMenu from './context-menus/TasksContextMenu'
import { handleDragEnd, handleDragStart } from '../redux/handleDrag'
import { selectCouriers, selectSplitDirection, selectAreToursEnabled, selectSearchIsOn } from '../redux/selectors'
import { useDispatch, useSelector } from 'react-redux'

const DashboardApp = ({ loadingAnim }) => {

  const dispatch = useDispatch()

  const toursEnabled = useSelector(selectAreToursEnabled)
  const couriersList = useSelector(selectCouriers)
  const searchIsOn = useSelector(selectSearchIsOn)
  const splitDirection = useSelector(selectSplitDirection)

  const splitRef = useRef(),
    splitCollapseAction = () => {
      if (!toursEnabled) {
        dispatch(setToursEnabled(true))
        splitRef.current.split.setSizes([33.33, 33.33, 33.33])
      } else {
        dispatch(setToursEnabled(false))
        splitRef.current.split.collapse(1)
      }
    }

  const children = [
    <UnassignedTasks key="split_unassigned" />,
    <UnassignedTours key="split_unassigned_tours" splitCollapseAction={ splitCollapseAction } />,
    <TaskLists key="split_task_lists" couriersList={ couriersList } />
  ]

  const sizes = toursEnabled ? [33.33, 33.33, 33.33] : [50, 0 , 50]

  useEffect(() => {
    const toggleSearchOnKeyDown = e => {
      const isCtrl = (e.ctrlKey || e.metaKey)
      if (e.keyCode === 114 || (isCtrl && e.keyCode === 70)) {
        if (!searchIsOn) {
          e.preventDefault()
          dispatch(toggleSearch())
        }
      }
      if (e.keyCode === 27) {
        dispatch(closeSearch())
      }
    }
    window.addEventListener('keydown', toggleSearchOnKeyDown)

    dispatch(loadOrganizations())

    loadingAnim.stop()
    loadingAnim.destroy()
    document.querySelector('.dashboard__loader').remove()

    // return cleanup function
    return () => {
      window.removeEventListener('keydown', toggleSearchOnKeyDown, false)
    }
  }, [])

  return (
    <div className="dashboard__aside-container">
      <DragDropContext
        // https://github.com/atlassian/@hello-pangea/dnd/blob/master/docs/patterns/multi-drag.md
        onDragStart={ (result) => dispatch(handleDragStart(result)) }
        onDragEnd={ (result) => dispatch(handleDragEnd(result)) }>
        <Split
          ref={ splitRef }
          sizes={ sizes }
          direction={ splitDirection }
          minSize={ splitDirection === 'vertical' ? 50 : 25 }
          style={{ display: 'flex', flexDirection: splitDirection === 'vertical' ? 'column' : 'row', width: '100%' }}
          // We need to use a "key" prop,
          // to force a re-render when the direction has changed
          key={ (splitDirection + (toursEnabled ? '-tours' : '')) }>
          { children }
        </Split>
      </DragDropContext>
      <SearchPanel />
      <TasksContextMenu />
      <ToastContainer />
    </div>
  )
}

export default DashboardApp
