import React, { Component } from 'react'
import { connect } from 'react-redux'
import {toggleShowFinishedTasks, setSelectedTagList} from "../store/actions"


class Filters extends Component {


  onClick(e) {
    e.preventDefault()
    this.props.toggleFinishedTasks()
  }

  onTagClick(e, tag) {
    e.preventDefault()
    this.props.setSelectedTagList(tag)
  }

  render() {
    const { showFinishedTasks, selectedTags, allTags, debug_state } = this.props

    let selectedTagsName = _.map(selectedTags, tag => tag.name)

    let tagsComponents = _.map(allTags, (tag) => {
      return (
        <a key={tag.name} className="dropdown-item" onClick={(e) => this.onTagClick(e, tag)}>{selectedTagsName.includes(tag.name) && (<i className="fa fa-check dashboard__filters__icon"></i>)}
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
    debug_state: state,
    selectedTags: state.tagsFilters.selectedTags,
  }
}

function mapDispatchToProps (dispatch) {
  return {
    toggleFinishedTasks: () => { dispatch(toggleShowFinishedTasks()) },
    setSelectedTagList: tagName => {  dispatch(setSelectedTagList(tagName)) },
    }
  }


export default connect(mapStateToProps, mapDispatchToProps)(Filters)
