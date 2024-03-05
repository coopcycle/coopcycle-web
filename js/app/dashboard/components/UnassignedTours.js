import React from 'react'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Draggable, Droppable } from "@hello-pangea/dnd"

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


class UnassignedTours extends React.Component {

  render() {
    return (
      <div className="dashboard__panel">
        <h4 className="d-flex justify-content-between">
          <span>{ this.props.t('DASHBOARD_UNASSIGNED_TOURS') }</span>
          <span>
            <Buttons />
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
          <Droppable 
            droppableId="unassigned_tours" 
            key={this.props.tours.length} // assign a mutable key to trigger a re-render when inserting a nested droppable (for example : a new tour)
            >
            {(provided) => (
              <div className="list-group nomargin" ref={ provided.innerRef } { ...provided.droppableProps }>
                { _.map(this.props.groups, (group, index) => {
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
                            onConfirmDelete={ () => this.props.deleteGroup(group) }
                            onEdit={ (data) => this.props.editGroup(data) } />
                        </div>
                      )}
                    </Draggable>
                  )
                })}
                { _.map(this.props.tours, (tour, index) => {
                  return (
                    <Draggable key={ `tour-${tour['@id']}` } draggableId={ `tour:${tour['@id']}` } index={ this.props.groups.length + index }>
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
}

function mapStateToProps (state) {

  return {
    groups: selectGroups(state),
    tours: selectUnassignedTours(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    deleteGroup: (group) => dispatch(deleteGroup(group)),
    editGroup: (group) => dispatch(editGroup(group)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(UnassignedTours))
