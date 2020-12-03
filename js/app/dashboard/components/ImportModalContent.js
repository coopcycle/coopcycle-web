import React, { useEffect } from 'react'
import { withTranslation } from 'react-i18next'
import { connect } from 'react-redux'
import Dropzone from 'dropzone'
import { toast } from 'react-toastify'
import _ from 'lodash'

Dropzone.autoDiscover = false

import "dropzone/dist/dropzone.css"

import { closeImportModal, addImport } from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'

const mimeTypes = [
  'application/vnd.oasis.opendocument.spreadsheet',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/octet-stream',
  'application/vnd.ms-excel',
  'text/csv',
  'text/plain'
]

const ImportModalContent = ({ addImport, closeImportModal, date, t, url, exampleUrl }) => {

  const ref = React.createRef()

  useEffect(() => {

    // componentDidMount

    const dz = new Dropzone(ref.current, {
      url,
      dictDefaultMessage: t('DROPZONE_DEFAULT_MESSAGE'),
      maxFiles: 1,
      params: {
        type: 'tasks',
        date: date.format('YYYY-MM-DD'),
      },
      // Set clickable = false, to avoid limiting file explorers
      // behaving differently on different operatin systems
      clickable: false,
      accept: function(file, done) {

        // @see https://github.com/react-dropzone/react-dropzone/issues/276
        if (file.type === '' && (file.name.endsWith('.csv') || file.name.endsWith('.xlsx'))) {
          done()
          return
        }

        if (!_.includes(mimeTypes, file.type)) {
          done(t('DROPZONE_INVALID_FILE_TYPE', { type: file.type }))
          return
        }

        done()
      },
      init: function() {

        this.on('success', function(file, response) {
          closeImportModal()
          addImport(response.token)
          toast(t('ADMIN_DASHBOARD_TASK_IMPORT_PROCESSING'))
        })

        // TODO Allow removing file inside modal
        // this.on('error', function(file, errorMessage, jqXHR) {
        //   file.previewElement.addEventListener("click", function() {
        //     dz.removeFile(file);
        //   })
        // })

      }
    })

    return () => {
      // componentWillUnmount
      dz.destroy()
    }
  }, [])

  return (
    <div>
      <div className="dropzone dropzone--blue mb-3" ref={ ref }></div>
      <small className="text-muted d-block text-center mb-2">{ t('ADMIN_DASHBOARD_IMPORT_FILE_FORMATS') }</small>
      <a className="d-block text-center" href={ exampleUrl }>{ t('DOWNLOAD_EXAMPLE_SPREADSHEET') }</a>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    date: selectSelectedDate(state),
    url: state.uploaderEndpoint,
    exampleUrl: state.exampleSpreadsheetUrl,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeImportModal: () => dispatch(closeImportModal()),
    addImport: token => dispatch(addImport(token)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(ImportModalContent))
