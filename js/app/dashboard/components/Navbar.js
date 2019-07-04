import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import ReactDOMServer from 'react-dom/server'
import moment from 'moment'
import _ from 'lodash'
import DatePicker from 'antd/lib/date-picker'
import LocaleProvider from 'antd/lib/locale-provider'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'

import Filters from './Filters'

const locale = $('html').attr('lang'),
  antdLocale = locale === 'fr' ? fr_FR : en_GB

class Navbar extends React.Component {

  componentDidMount() {
    if (this.props.hasUploadErrors) {
      $('#task-upload-form-errors').popover({
        html: true,
        container: 'body'
      })
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
                    <LocaleProvider locale={antdLocale}>
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
                    </LocaleProvider>
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
                <a href="#" data-toggle="modal" data-target="#upload-modal">
                  <i className="fa fa-upload" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_IMPORT') }
                </a>
              </li>
              { this.props.hasUploadErrors && (
                <li>
                  <a id="task-upload-form-errors" href="#"
                    data-toggle="popover" data-placement="bottom"
                    data-content={ ReactDOMServer.renderToString(this.renderErrors()) }>
                    <span className="text-danger"><i className="fa fa-exclamation-circle" aria-hidden="true"></i> { this.props.t('ADMIN_DASHBOARD_NAV_IMPORT_ERRORS') }</span>
                  </a>
                </li>
              )}
              <li className="dropdown" id="dashboard-filters">
                <a className="admin-navbar__link" href="#" role="button"
                  aria-haspopup="true" aria-expanded="false">
                  { this.props.t('ADMIN_DASHBOARD_NAV_FILTERS') } <span className="caret"></span>
                </a>
                <Filters />
              </li>
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
      date: state.date.subtract(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    next: window.Routing.generate('admin_dashboard_fullscreen', {
      date: state.date.add(1, 'days').format('YYYY-MM-DD'),
      nav: state.nav
    }),
    hasUploadErrors: state.taskUploadFormErrors.length > 0,
    uploadErrors: state.taskUploadFormErrors,
    nav: state.nav
  }
}

export default connect(mapStateToProps)(translate()(Navbar))
