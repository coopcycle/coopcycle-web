import React, { Component } from 'react'
import { connect } from 'react-redux'
import {toggleShowFinishedTasks, toggleShowTaggedTasks} from "../store/actions"


class Filters extends Component {

  onClick(e) {
    e.preventDefault()
    this.props.toggleFinishedTasks()
  }

  onTagClick(e, tagName) {
    e.preventDefault()
    this.props.toggleTaggedTasks(tagName)
  }


  render() {
    const { showFinishedTasks, allTags } = this.props

    let tagsComponents = allTags.map((tag) => {
      return (
        <a className="dropdown-item" onClick={(e) => this.onTagClick(e, tag.name)}>
        <i className="fa fa-check dashboard__filters__icon"></i>
          <span className="label label-default" style={{backgroundColor: tag.color}}>{tag.name}</span>
        </a>
      )
    })

    return (
      <li className="dropdown">
        <a className="admin-navbar__link dropdown-toggle" href="#" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        Filtres <span className="caret"></span>
      </a>
      <ul className="dropdown-menu">
        <li>
          <a onClick={(e) => this.onClick(e)}>{ showFinishedTasks && (<i className="fa fa-check dashboard__filters__icon"></i>)}
            Tâches terminées
          </a>
          {tagsComponents}
        </li>
      </ul>
    </li>
  )
  }
}

function mapStateToProps (state) {
  return {
    showFinishedTasks: state.tasksFilters.showFinishedTasks,
    allTags: state.allTags,
  }
}

function mapDispatchToProps (dispatch) {
  return {
    toggleFinishedTasks: () => { dispatch(toggleShowFinishedTasks()) },
    toggleTaggedTasks: tagName => {  dispatch(toggleShowTaggedTasks(tagName)) },
    }
  }

export default connect(mapStateToProps, mapDispatchToProps)(Filters)
