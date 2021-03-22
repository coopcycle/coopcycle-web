import React from 'react'
import { connect } from 'react-redux'
import { DragDropContext } from 'react-beautiful-dnd'
import Split from 'react-split'

import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'

import {
  toggleSearch,
  closeSearch,
  handleDragStart,
  handleDragEnd } from '../redux/actions'
import UnassignedTasks from './UnassignedTasks'
import TaskLists from './TaskLists'
import ContextMenu from './ContextMenu'
import SearchPanel from './SearchPanel'

class DashboardApp extends React.Component {

  componentDidMount() {
    window.addEventListener('keydown', e => {
      const isCtrl = (e.ctrlKey || e.metaKey)
      if (e.keyCode === 114 || (isCtrl && e.keyCode === 70)) {
        if (!this.props.searchIsOn) {
          e.preventDefault()
          this.props.toggleSearch()
        }
      }
      if (e.keyCode === 27) {
        this.props.closeSearch()
      }
    })
  }

  render () {
    return (
      <div className="dashboard__aside-container">
        <DragDropContext
          // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md
          onDragStart={ this.props.handleDragStart }
          onDragEnd={ this.props.handleDragEnd }>
          <Split
            sizes={ [ 50, 50 ] }
            direction={ this.props.splitDirection }
            style={{ display: 'flex', flexDirection: this.props.splitDirection === 'vertical' ? 'column' : 'row', width: '100%' }}
            // We need to use a "key" prop,
            // to force a re-render when the direction has changed
            key={ this.props.splitDirection }>
            <UnassignedTasks />
            <TaskLists couriersList={ this.props.couriersList } />
          </Split>
        </DragDropContext>
        <SearchPanel />
        <ContextMenu />
        <ToastContainer />
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    couriersList: state.config.couriersList,
    searchIsOn: state.searchIsOn,
    splitDirection: state.rightPanelSplitDirection,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    toggleSearch: () => dispatch(toggleSearch()),
    closeSearch: () => dispatch(closeSearch()),
    handleDragStart: (result) => dispatch(handleDragStart(result)),
    handleDragEnd: (result) => dispatch(handleDragEnd(result)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
