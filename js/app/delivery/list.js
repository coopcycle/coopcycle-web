import DatePicker from '../widgets/DatePicker'
import DateRangePicker from '../widgets/DateRangePicker'
import Input from '../widgets/Input'
import axios from 'axios'
import Papa from 'papaparse'
import classNames from 'classnames'
import _ from 'lodash'
import { Tooltip } from 'antd'
import Modal from 'react-modal'

import React, { useState } from 'react'
import Spreadsheet from 'react-spreadsheet'
import { render } from 'react-dom'
import Centrifuge from 'centrifuge'

import './list.scss'

['start', 'end'].forEach(name => {
  const inputEl = document.querySelector(`#data_export_${name}`)
  const widgetEl = document.querySelector(`#data_export_${name}_widget`)
  if (inputEl && widgetEl) {
    new DatePicker(widgetEl, {
      onChange: function(date) {
        if (date) {
          inputEl.value = date.format('YYYY-MM-DD');
        }
      }
    })
  }
})

const startEl = document.getElementById('start_at')
const endEl = document.getElementById('end_at')
const dateRangeWidgetEl = document.getElementById('daterange_widget')

if (startEl && endEl && dateRangeWidgetEl) {
  let options = {
    showTime: false,
    format: 'DD MMM',
    onChange: function ({after, before}) {
      startEl.value = after.format('YYYY-MM-DD')
      endEl.value = before.format('YYYY-MM-DD')
    }
  }

  if (startEl.value && endEl.value) {
    options = {
      defaultValue: {
        before: startEl.value,
        after: endEl.value
      },
      ...options
    }
  }

  new DateRangePicker(dateRangeWidgetEl, options)
}

const searchEl = document.getElementById('search_input')
const searchWidgetEl = document.getElementById('search_input_widget')

if (searchEl && searchWidgetEl) {
  new Input(searchWidgetEl, {
    allowClear: true,
    placeholder: searchEl.placeholder,
    defaultValue: searchEl.value,
    onChange: function(e) {
      searchEl.value = e.target.value
    }
  })
}

function SpreadsheetViewer({ data, errors }) {

  const [ isOpen, setIsOpen ] = useState(true)

  function closeModal(e) {
    e.preventDefault()
    setIsOpen(false)
  }

  const errorsByRow = _.keyBy(errors, error => error.row)
  const rowsWithErrors = _.keys(errorsByRow)

  return (
    <Modal
      isOpen={ isOpen }
      className="ReactModal__Content--delivery-import-spreadsheet"
      shouldCloseOnOverlayClick={ true }
    >
      <header className="text-right mb-4"><a href="#" onClick={ closeModal }><i className="fa fa-close" /></a></header>
      <Spreadsheet
      data={ data }
      // https://github.com/iddan/react-spreadsheet/blob/5751105cd84db1bb745fa37d399ebedcb48220df/src/RowIndicator.tsx
      RowIndicator={ ({ selected, row }) => {

        const hasErrors = rowsWithErrors.includes('' + row)

        const style = hasErrors ? {
          top: -5,
          position: "absolute",
          right: 1,
          width: 0,
          height: 0,
          borderTop: "8px solid transparent",
          borderBottom: "8px solid transparent",
          borderLeft: "8px solid red",
          transform: "rotate(-45deg)",
        } : {}

        return (
          <th
            className={ classNames('Spreadsheet__header', {
              'Spreadsheet__header--selected': selected,
            })}
            tabIndex={ 0 }
          >
            <span style={{ display: 'inline-block', width: '100%', position: 'relative' }}>
              { hasErrors &&
              <Tooltip title={ errorsByRow[row].errors.join('\n') } color="red">
                <span style={ style } />
              </Tooltip>
              }
              <span>{ row + 1 }</span>
            </span>
          </th>
        );
      } }
      />
    </Modal>
  )
}

const importStatusIcon = {
  'pending': 'clock-o',
  'started': 'play',
  'completed': 'check',
  'failed': 'exclamation-circle'
}

function addSpreadsheetView(viewIcon) {
  viewIcon.addEventListener('click', e => {

    const parentEl = e.target.closest('[data-delivery-import]')

    e.preventDefault()

    Promise.all(
      [
        axios({
          method: 'get',
          url: parentEl.dataset.deliveryImportCsv,
          headers: {
            Authorization: `Bearer ${window._auth.jwt}`
          }
        }),
        axios({
          method: 'get',
          url: parentEl.dataset.deliveryImportQueue,
          headers: {
            Authorization: `Bearer ${window._auth.jwt}`
          }
        })
      ]
    ).then(values => {

      const [ csvResponse, queueResponse ] = values

      const errors = queueResponse.data.errors
      const parseResult = Papa.parse(csvResponse.data)

      const data = parseResult.data.map(row => row.map(value => ({ value })))

      render(<SpreadsheetViewer
        key={ parentEl.dataset.deliveryImportQueue }
        data={ data }
        errors={ errors } />, document.getElementById('delivery_import_spreadsheet'))
    })
  }, false)
}

function addRedownload(el) {
  el.addEventListener('click', function (e) {

    e.preventDefault()

    const parentEl = e.target.closest('[data-delivery-import]')

    axios({
      method: 'get',
      url: parentEl.dataset.deliveryImportRedownload,
      headers: {
        Authorization: `Bearer ${window._auth.jwt}`
      }
    }).then(response => {

      const basenameWithoutExtension = parentEl.dataset.deliveryImportFilename.replace(/\.[^/.]+$/, "")

      const file = new File([ response.data ], `${basenameWithoutExtension}.csv`, {
        type: 'text/csv',
      })

      const link = document.createElement('a')
      const url = URL.createObjectURL(file)

      link.href = url
      link.download = file.name
      document.body.appendChild(link)
      link.click()

      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    })
  }, false)
}

const deliveryImports = document.querySelector('[data-delivery-imports]')
if (deliveryImports) {

  const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'
  const centrifuge = new Centrifuge(`${protocol}://${window.location.host}/centrifugo/connection/websocket`)
  centrifuge.setToken(deliveryImports.dataset.centrifugoToken)

  centrifuge.subscribe(deliveryImports.dataset.centrifugoChannel, function(message) {
    const { event } = message.data
    if (event.name === 'delivery_import:updated') {
      const row = document.querySelector(`[data-delivery-import-filename="${event.data.filename}"]`)
      const statusIcon = row.querySelector('[data-delivery-import-status]')
      const viewIcon = row.querySelector('[data-delivery-import-view]')
      const downloadIcon = row.querySelector('[data-delivery-import-redownload]')
      statusIcon.classList.remove(...statusIcon.classList)
      statusIcon.classList.add('fa', `fa-${importStatusIcon[event.data.status]}`)
      if (event.data.status === 'failed') {
        row.classList.add('danger')
        viewIcon.classList.remove('d-none')
        downloadIcon.classList.remove('d-none')
        addSpreadsheetView(viewIcon)
        addRedownload(downloadIcon)
      }
    }
  })

  centrifuge.connect()

  Modal.setAppElement('#delivery_import_spreadsheet')

  deliveryImports.querySelectorAll('[data-delivery-import]').forEach(el => {
    addSpreadsheetView(el.querySelector('[data-delivery-import-view]'))
    addRedownload(el.querySelector('[data-delivery-import-redownload]'))
  })
}

const deliveryImportsDatePicker = document.querySelector('#import-datepicker')
if (deliveryImportsDatePicker) {
  new DatePicker(deliveryImportsDatePicker, {
    defaultValue: deliveryImportsDatePicker.dataset.defaultDate,
    onChange: function(date) {
      window.location.href = window.Routing.generate('admin_deliveries', { section: 'imports', date: date.format('YYYY-MM-DD') });
    }
  });
}
