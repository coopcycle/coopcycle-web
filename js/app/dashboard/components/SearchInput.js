import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import { selectFuseSearch } from '../redux/selectors'

import Task from './Task'

import 'antd/lib/button/style/index.css'

class SearchInput extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      q: '',
      results: [],
    }

    this.searchRef = React.createRef()
    this.search = _.debounce(this._search.bind(this), 100)

    this.wrapperRef = React.createRef()
    this.handleClickOutside = this.handleClickOutside.bind(this)
    this.toggleSearchOnKeyDown = this.toggleSearchOnKeyDown.bind(this);
  }

  componentDidMount() {
    window.addEventListener('keydown', this.toggleSearchOnKeyDown)
    document.addEventListener("mousedown", this.handleClickOutside)
  }

  componentWillUnmount() {
    document.removeEventListener("mousedown", this.handleClickOutside)
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

  handleClickOutside(event) {
    if (this.wrapperRef && !this.wrapperRef.current.contains(event.target)) {
      this.setState({
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

    return (
      <div ref={this.wrapperRef}>
        <div className="dashboard__panel__search-box">
          <div className="dashboard__panel__search-box__input-wrapper">
            <input
              value={ this.state.q }
              placeholder={ this.props.t('ADMIN_DASHBOARD_SEARCH_PLACEHOLDER') }
              onChange={ e => {
                this.setState({ q: e.target.value })
                this.search(e.target.value)
              }}
              onFocus={() => this.search(this.state.q)}
              ref={ (input) => this.searchRef = input }
            />
            { this.state.q && (
              <button className="dashboard__panel__search-box__clear" onClick={ () => this.setState({ q: '', results: [] }) }>
                <i className="fa fa-times-circle"></i>
              </button>
            )}
          </div>
        </div>
        { this.state.results.length > 0 ?
          <div className="dashboard__panel__search-results list-group nomargin">
            { _.map(this.state.results, (task, key) => {
              return (
                <Task
                  key={ key }
                  taskId={ task['@id'] }
                  toggleTask={ this.props.toggleTask }
                  selectTask={ this.props.selectTask }
                  taskWithoutDrag
                />
              )
            })}
          </div> :
          null
        }
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    fuse: selectFuseSearch(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(SearchInput))
