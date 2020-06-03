import React, { useEffect } from 'react'
import { withTranslation } from 'react-i18next'
import Dropzone from 'dropzone'
import _ from 'lodash'

Dropzone.autoDiscover = false

import "dropzone/dist/dropzone.css"

const mimeTypes = [
  'application/vnd.oasis.opendocument.spreadsheet',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/octet-stream',
  'application/vnd.ms-excel',
  'text/csv',
  'text/plain'
]

const SpreadsheetDropzone = ({ onSuccess, params, t, url }) => {

  const ref = React.createRef()

  useEffect(() => {

    // componentDidMount

    const dz = new Dropzone(ref.current, {
      url,
      dictDefaultMessage: t('DROPZONE_DEFAULT_MESSAGE'),
      maxFiles: 1,
      params,
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
          if (onSuccess && typeof onSuccess === 'function') {
            onSuccess(file, response)
          }
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
      <span className="text-muted">{ t('ADMIN_DASHBOARD_IMPORT_FILE_FORMATS') }</span>
    </div>
  )
}

export default withTranslation()(SpreadsheetDropzone)
