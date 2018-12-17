import React, { Component } from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import {
  toggleShowFinishedTasks,
  toggleShowCancelledTasks,
  setSelectedTagList,
  toggleShowUntaggedTasks
} from '../store/actions'
import { translate } from 'react-i18next'

class Filters extends Component {

  onShowFinishedClick(e) {
    e.preventDefault()
    this.props.toggleFinishedTasks()
  }

  onShowCancelledClick(e) {
    e.preventDefault()
    this.props.toggleCancelledTasks()
  }

  onTagClick(e, tag) {
    e.preventDefault()
    this.props.setSelectedTagList(tag)
  }

  onShowUntaggedClick(e, tag) {
    e.preventDefault()
    this.props.toggleShowUntaggedTasks(tag)
  }

  render() {
    const { showFinishedTasks, showCancelledTasks, selectedTags, showUntaggedTasks } = this.props

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
          <a onClick={(e) => this.onShowFinishedClick(e)}>
            { showFinishedTasks ? (<i className="fa fa-check dashboard__filters__icon"></i>) : (<i className="dashboard__filters__icon"></i>)}
            { this.props.t('ADMIN_DASHBOARD_FILTERS_COMPLETED_TASKS') }
          </a>
        </li>
        <li>
          <a onClick={(e) => this.onShowCancelledClick(e)}>
            { showCancelledTasks ? (<i className="fa fa-check dashboard__filters__icon"></i>) : (<i className="dashboard__filters__icon"></i>)}
            { this.props.t('ADMIN_DASHBOARD_FILTERS_CANCELLED_TASKS') }
          </a>
        </li>
        <li>
          <a onClick={(e) => this.onShowUntaggedClick(e)}>
            { showUntaggedTasks ? (<i className="fa fa-check dashboard__filters__icon"></i>) : (<i className="dashboard__filters__icon"></i>)}
            { this.props.t('ADMIN_DASHBOARD_FILTERS_NONTAGGED_TASKS') }
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
    showCancelledTasks: state.taskCancelledFilter,
    selectedTags: state.tagsFilter.selectedTagsList,
    showUntaggedTasks: state.tagsFilter.showUntaggedTasks,
  }
}

function mapDispatchToProps (dispatch) {
  return {
    toggleFinishedTasks: () => { dispatch(toggleShowFinishedTasks()) },
    toggleCancelledTasks: () => { dispatch(toggleShowCancelledTasks()) },
    setSelectedTagList: (tagName) => {  dispatch(setSelectedTagList(tagName)) },
    toggleShowUntaggedTasks: () => { dispatch(toggleShowUntaggedTasks())}
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(Filters))
