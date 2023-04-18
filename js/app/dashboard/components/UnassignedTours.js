import React from 'react'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"

import UnassignedTour from './UnassignedTour'
import { selectUnassignedTours } from '../redux/selectors'


class UnassignedTours extends React.Component {

  render() {
    return (
      <div className="dashboard__panel">
        <h4 className="d-flex justify-content-between">
          <span>{ this.props.t('DASHBOARD_UNASSIGNED_TOURS') }</span>
        </h4>
        <div className="dashboard__panel__scroll">
          <Droppable isDropDisabled={ this.props.isDropDisabled } droppableId="unassigned_tours">
            {(provided) => (
              <div className="list-group nomargin" ref={ provided.innerRef } { ...provided.droppableProps }>
                { _.map(this.props.tours, (tour, index) => {
                  return (
                    <Draggable key={ `tour-${tour['@id']}` } draggableId={ `tour:${tour['@id']}` } index={ index }>
                      {(provided) => (
                        <div
                          ref={ provided.innerRef }
                          { ...provided.draggableProps }
                          { ...provided.dragHandleProps }
                        >
                          <UnassignedTour
                            key={ tour['@id'] }
                            tour={ tour }
                            tasks={ tour.items }
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
    tours: selectUnassignedTours(state),
    isDropDisabled: state.logistics.ui.unassignedToursDroppableDisabled
  }
}

export default connect(mapStateToProps)(withTranslation()(UnassignedTours))
