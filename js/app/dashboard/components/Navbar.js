import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'
import { ConfigProvider, DatePicker } from 'antd'
import _ from 'lodash'

import { antdLocale } from '../../i18n'
import { openFiltersModal, resetFilters, openSettings, openImportModal } from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'

class Navbar extends React.Component {

  componentDidUpdate() {
    _.map(this.props.imports, (message, token) => {
      const $target = $(`#task-import-${token}`)
      if (message && !$target.data('bs.popover')) {
        $target.popover({
          html: true,
          container: 'body',
          placement: 'bottom',
          content: message
        })
      }
    })
  }

  _onFiltersClick(e) {
    e.preventDefault()
    this.props.openFiltersModal()
  }

  _onSettingsClick(e) {
    e.preventDefault()
    this.props.openSettings()
  }

  _onClearClick(e) {
    e.preventDefault()
    this.props.resetFilters()
  }

  _onImportClick(e) {
    e.preventDefault()
    this.props.openImportModal()
  }

  renderFilters() {

    const text = (
      <span className={ this.props.isDefaultFilters ? '' : 'text-primary' }>
        <i className="fa fa-sliders" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_FILTERS') }
      </span>
    )

    if (!this.props.isDefaultFilters) {
      return (
        <strong>
          { text }
        </strong>
      )
    }

    return text
  }

  renderReset() {

    return (
      <li>
        <a href="#" onClick={ this._onClearClick.bind(this) }>
          <span className="text-muted">
            <i className="fa fa-times-circle" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_FILTERS_CLEAR') }
          </span>
        </a>
      </li>
    )
  }

  renderImport(token, message) {

    return (
      <li key={ token } className="active">
        <a href="#" className={ message ? 'text-danger': '' } id={ `task-import-${token}` }>
          { !message && (<i className="fa fa-spinner fa-spin mr-2"></i>) }
          { message && (<i className="fa fa-exclamation-circle mr-2"></i>) }
          <span>{ token }</span>
        </a>
      </li>
    )
  }

  render () {

    return (
      <nav className="navbar navbar-default">
        <div className="container-fluid">
          <div className="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul className="nav navbar-nav" id="dashboard-controls">
              <li>
                <div className="dashboard__date">
                  <a className="dashboard__date-link" href={ this.props.prev }>
                    <i className="fa fa-caret-left"></i>
                  </a>
                  <div className="dashboard__date-picker">
                    <ConfigProvider locale={antdLocale}>
                      <DatePicker
                        format={ 'll' }
                        defaultValue={ this.props.date }
                        onChange={(date) => {
                          if (date) {
                            window.location.href = window.Routing.generate('admin_dashboard_fullscreen', {
                              date: date.format('YYYY-MM-DD'),
                              nav: this.props.nav
                            })
                          }
                        }} />
                    </ConfigProvider>
                  </div>
                  <a className="dashboard__date-link" href={ this.props.next }>
                    <i className="fa fa-caret-right"></i>
                  </a>
                </div>
              </li>
              <li>
                <a href="#" data-toggle="modal" data-target="#export-modal">
                  <i className="fa fa-download" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_EXPORT') }
                </a>
              </li>
              <li>
                <a href="#"  onClick={ this._onImportClick.bind(this) }>
                  <i className="fa fa-upload" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_IMPORT') }
                </a>
              </li>
              { _.size(this.props.imports) > 0 && _.map(this.props.imports, (message, token) => this.renderImport(token, message))}
              <li>
                <a href="#" onClick={ this._onFiltersClick.bind(this) }>
                  { this.renderFilters() }
                </a>
              </li>
              { !this.props.isDefaultFilters && this.renderReset() }
            </ul>
            <ul className="nav navbar-nav navbar-right">
              <li>
                <a href="#" onClick={ this._onSettingsClick.bind(this) }>
                  <i className="fa fa-cog mr-1" aria-hidden="true"></i>
                  <span className="d-none d-xl-inline">{ this.props.t('ADMIN_DASHBOARD_NAV_SETTINGS') }</span>
                </a>
              </li>
              <li><a><span className="pulse" id="pulse"></span></a></li>
            </ul>
          </div>
        </div>
      </nav>
    )
  }
}

function mapStateToProps(state) {
  let selectedDate = selectSelectedDate(state)

  return {
    date: selectedDate,
    prev: window.Routing.generate('admin_dashboard_fullscreen', {
      date: moment(selectedDate).subtract(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    next: window.Routing.generate('admin_dashboard_fullscreen', {
      date: moment(selectedDate).add(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    imports: state.imports,
    nav: state.nav,
    isDefaultFilters: state.isDefaultFilters,
    taskImportToken: state.taskImportToken,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    openFiltersModal: () => dispatch(openFiltersModal()),
    resetFilters: () => dispatch(resetFilters()),
    openSettings: () => dispatch(openSettings()),
    openImportModal: () => dispatch(openImportModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Navbar))
