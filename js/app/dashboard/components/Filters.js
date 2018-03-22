import React, { Component } from 'react'
import { connect } from 'react-redux'
import {toggleShowFinishedTasks} from "../store/actions"


class Filters extends Component {

  onClick(e) {
    e.preventDefault()
    this.props.toggleFinishedTasks()
  }

  render() {
    const { showFinishedTasks } = this.props

    return (
      <ul className="dropdown-menu">
        <li>
          <a onClick={(e) => this.onClick(e)}>{ showFinishedTasks && (<i className="fa fa-check dashboard__filters__icon"></i>)}
            Tâches terminées
          </a>
        </li>
      </ul>
    )
  }
}

function mapStateToProps (state) {
  return {
    showFinishedTasks: state.tasksFilters.showFinishedTasks
  }
}

function mapDispatchToProps (dispatch) {
  return {
    toggleFinishedTasks: () => { dispatch(toggleShowFinishedTasks()) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Filters)
