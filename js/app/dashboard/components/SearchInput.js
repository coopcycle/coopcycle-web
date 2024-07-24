import React, { createRef } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import { selectFuseSearch } from '../redux/selectors'

import Task from './Task'

import { AutoComplete, Input, Select } from 'antd'


class SearchInput extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      q: '',
      results: [],
    }

    this.searchRef = createRef()
    this.search = _.debounce(this._search.bind(this), 100)
    this.toggleSearchOnKeyDown = this.toggleSearchOnKeyDown.bind(this);
  }

  componentDidMount() {
    window.addEventListener('keydown', this.toggleSearchOnKeyDown)
  }

  componentWillUnmount() {
    window.removeEventListener('keydown', this.toggleSearchOnKeyDown, false)
  }

  toggleSearchOnKeyDown = e => {
    const isCtrl = (e.ctrlKey || e.metaKey)
    // 114: F3, 70: f
    if (e.keyCode === 114 || (isCtrl && e.keyCode === 70)) {
      e.preventDefault()
      this.searchRef.focus()
    }
    // 27 : escape
    if (e.keyCode === 27) {
      this.setState({
        q: '',
        results: [],
      })
      this.searchRef.blur()
    }
  }

  _search(q) {
    const results = this.props.fuse.search(q)
    this.setState({
      results: results.map(result => result.item)
    })
  }

  render () {
    const resultsDisplay = _.map(this.state.results, (task, index) => {
      return (
        <Select.Option key={index} className="dashboard__search-result">
          <Task
            key={ index }
            taskId={ task['@id'] }
            toggleTask={ this.props.toggleTask }
            selectTask={ this.props.selectTask }
            taskWithoutDrag
          />
        </Select.Option>
      )})

    return (
      <AutoComplete
        ref={this.searchRef}
        style={{"minWidth":"300px"}}
        value={ this.state.q }
        onSearch={ value => {
          this.setState({ q: value })
          this.search(value)
        }}
        dataSource={resultsDisplay}
        dropdownStyle={{zIndex: 1, height: "30vh"}}
      >
        <Input.Search placeholder={ this.props.t('ADMIN_DASHBOARD_SEARCH_PLACEHOLDER') } />
      </AutoComplete>
    )
  }
}

function mapStateToProps(state) {

  return {
    fuse: selectFuseSearch(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(SearchInput))
