import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import ReactDOMServer from 'react-dom/server'
import moment from 'moment'
import { ConfigProvider, DatePicker } from 'antd'
import fr_FR from 'antd/es/locale/fr_FR'
import en_GB from 'antd/es/locale/en_GB'
import { toast } from 'react-toastify'

import { openFiltersModal, resetFilters, openSettings } from '../redux/actions'

const locale = $('html').attr('lang'),
  antdLocale = locale === 'fr' ? fr_FR : en_GB

class Navbar extends React.Component {

  componentDidMount() {
    if (this.props.taskImportToken) {
      toast(this.props.t('ADMIN_DASHBOARD_TASK_IMPORT_PROCESSING'))
    }
  }

  componentDidUpdate(prevProps) {
    if (!prevProps.hasUploadErrors && this.props.hasUploadErrors) {
      const $target = $('#task-upload-form-errors')
      if (!$target.data('bs.popover')) {
        $target.popover({
          html: true,
          container: 'body',
        })
      }
    }
  }

  renderErrors() {

    return (
      <ul className="list-unstyled nomargin">
        { this.props.uploadErrors.map((error, key) => (
          <li key={ key }>{ error.message }</li>
        )) }
      </ul>
    )
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

  renderImportButton() {

    if (this.props.taskImportToken) {
      return (
        <li>
          <a>
            <i className="fa fa-spinner fa-spin"></i> { this.props.t('ADMIN_DASHBOARD_TASK_IMPORT_PROCESSING') }
          </a>
        </li>
      )
    }

    return (
      <li>
        <a href="#" data-toggle="modal" data-target="#upload-modal">
          <i className="fa fa-upload" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_IMPORT') }
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
              { this.renderImportButton() }

              { this.props.hasUploadErrors && (
                <li>
                  <a id="task-upload-form-errors" href="#"
                    data-toggle="popover" data-placement="bottom"
                    data-content={ ReactDOMServer.renderToString(this.renderErrors()) }>
                    <span className="text-danger"><i className="fa fa-exclamation-circle" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_IMPORT_ERRORS') }</span>
                  </a>
                </li>
              )}
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
                  <i className="fa fa-cog" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_SETTINGS') }
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

  return {
    date: state.date,
    prev: window.Routing.generate('admin_dashboard_fullscreen', {
      date: moment(state.date).subtract(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    next: window.Routing.generate('admin_dashboard_fullscreen', {
      date: moment(state.date).add(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    hasUploadErrors: state.taskUploadFormErrors.length > 0,
    uploadErrors: state.taskUploadFormErrors,
    nav: state.nav,
    isDefaultFilters: state.isDefaultFilters,
    taskImportToken: state.taskImportToken,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    openFiltersModal: () => dispatch(openFiltersModal()),
    resetFilters: () => dispatch(resetFilters()),
    openSettings: () => dispatch(openSettings())
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Navbar))
