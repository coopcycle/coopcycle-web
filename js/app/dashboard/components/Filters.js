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
    const { showFinishedTasks, selectedTags } = this.props

    let selectedTagsNames = _.map(selectedTags, tag => tag.name)

    let tagsComponents = _.map(window.AppData.Dashboard.tags, (tag) => {
      return (
        <li key={tag.name}>
          <a className="dropdown-item" onClick={(e) => this.onTagClick(e, tag)}>
            {selectedTagsNames.includes(tag.name) ? (<i className="fa fa-check dashboard__filters__icon"></i>) : (<i className="dashboard__filters__icon"></i>)}
            <span className="label label-default" style={{backgroundColor: tag.color}}>{tag.name}</span>
          </a>
        </li>
      )
    })

    return (
      <ul className="dropdown-menu">
        <li>
          <a onClick={(e) => this.onClick(e)}>
            { showFinishedTasks ? (<i className="fa fa-check dashboard__filters__icon"></i>) : (<i className="dashboard__filters__icon"></i>)}
            Tâches terminées
          </a>
        </li>
        { tagsComponents }
      </ul>
    )
  }
}

function mapStateToProps (state) {
  return {
    showFinishedTasks: state.taskFinishedFilter,
    selectedTags: state.tagsFilter
  }
}

function mapDispatchToProps (dispatch) {
  return {
    toggleFinishedTasks: () => { dispatch(toggleShowFinishedTasks()) },
    setSelectedTagList: (tagName) => {  dispatch(setSelectedTagList(tagName)) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Filters)
