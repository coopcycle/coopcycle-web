import React from 'react'
import { connect } from "react-redux";

import { withTranslation } from 'react-i18next'
import Popconfirm from 'antd/lib/popconfirm'

require('gasparesganga-jquery-loading-overlay')

import Task from './Task'
import { selectExpandedTasksGroupsPanelsIds } from '../redux/selectors'
import { toggleTasksGroupPanelExpanded } from '../redux/actions'
import classNames from 'classnames';

class TaskGroup extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      group: props.group,
      editName: false,
      newName: '',
    }

    this.onEditPressed = this.onEditPressed.bind(this)
    this.onEditCancelled = this.onEditCancelled.bind(this)
  }

  renderTags() {
    const { group } = this.state

    if (!group.tags || group.tags.length === 0) {
      return;
    }

    return (
      <span className="task__tags d-flex mr-2">
        { group.tags.map(tag => (
          <i key={ tag.slug } className="fa fa-circle" style={{ color: tag.color }}></i>
        )) }
      </span>
    )
  }

  renderEditNameForm() {
    return (
      <form onSubmit={(e) => this.onEditSubmitted(e)} className="d-flex flex-grow-1">
        <input autoFocus type="text" name="group-name" className="mx-2 flex-grow-1 group__editable"
          value={this.state.newName}
          onChange={ (e) => this.setState({newName: e.target.value})}
          onKeyDown={e => e.key === 'Escape' ? this.onEditCancelled(e) : null }>
        </input>
        <div className="flex-grow-0">
          <a role="button" href="#" className="text-reset mr-3"
            onClick={ e => this.onEditSubmitted(e) }>
            <i className="fa fa-check"></i>
          </a>
          <a role="button" href="#" className="text-reset flex-grow-0"
            onClick={ e => this.onEditCancelled(e) }>
            <i className="fa fa-times"></i>
          </a>
        </div>
      </form>
    )
  }

  onEditPressed(e, group) {
    e.preventDefault()
    this.setState({editName: true, newName: group.name})
  }

  onEditCancelled(e) {
    e.preventDefault()
    this.setState({editName: false})
  }

  async onEditSubmitted(e) {
    e.preventDefault()
    $('.task__draggable').LoadingOverlay('show', {image: false})
    const group = Object.assign({}, this.state.group, {name: this.state.newName})
    const updatedGroup = await this.props.onEdit(group)
    this.setState({
      group: updatedGroup !== null ? updatedGroup : this.state.group,
      editName: false
    }, () => {
      $('.task__draggable').LoadingOverlay('hide')
    })
  }

  render() {
    const { tasks, toggleTasksGroupPanelExpanded, expandedTasksGroupPanelIds } = this.props
    const { group } = this.state

    const isExpanded = expandedTasksGroupPanelIds.includes(group['@id'])

    tasks.sort((a, b) => {
      return a.id > b.id ? 1 : -1
    })

    return (
      <div className="panel panel-default panel--group nomargin task__draggable">
        <div className="panel-heading" role="tab">
          <h4 className="panel-title d-flex align-items-center">
            <i className="fa fa-folder flex-grow-0"></i>
            {
              !this.state.editName &&
              <>
                <a role="button" onClick={() => toggleTasksGroupPanelExpanded(group['@id'])} href={ `#task-group-panel-${this.state.group.id}` } className="ml-2 flex-grow-1 text-truncate">
                  { this.state.group.name } <span className="badge">{ tasks.length }</span>
                </a>
                <i className="fa fa-arrows cursor--grabbing mr-2"></i>
                { this.renderTags() }
                <div className="d-flex flex-grow-0">
                  <a role="button" href="#" className="text-reset mr-2"
                    onClick={ e => this.onEditPressed(e, this.state.group) }>
                    <i className="fa fa-pencil"></i>
                  </a>
                  <Popconfirm
                    placement="left"
                    title={ this.props.t('ADMIN_DASHBOARD_DELETE_GROUP_CONFIRM') }
                    onConfirm={ this.props.onConfirmDelete }
                    okText={ this.props.t('CROPPIE_CONFIRM') }
                    cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }
                    >
                    <a role="button" href="#" className="text-reset"
                      onClick={ e => e.preventDefault() }>
                      <i className="fa fa-trash"></i>
                    </a>
                  </Popconfirm>
                </div>
              </>
            }
            { this.state.editName && this.renderEditNameForm() }
          </h4>
        </div>
        <div id={ `task-group-panel-${this.state.group.id}` } className={classNames("panel-collapse collapse", {"in": isExpanded})} role="tabpanel">
          <ul className="list-group">
            { tasks.map((task) => {
              return (
                <Task
                  key={ task['@id'] }
                  taskId={ task['@id'] }
                  taskWithoutDrag
                />
              )
            })}
          </ul>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state) {
  return {
    expandedTasksGroupPanelIds: selectExpandedTasksGroupsPanelsIds(state)
  }
}

function mapDispatchToProps(dispatch) {
  return {
    toggleTasksGroupPanelExpanded: (groupId) => dispatch(toggleTasksGroupPanelExpanded(groupId)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskGroup))
