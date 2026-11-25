import DatePicker from '../widgets/DatePicker'
import DateRangePicker from '../widgets/DateRangePicker'
import Input from '../widgets/Input'
import axios from 'axios'
import Papa from 'papaparse'
import clsx from 'clsx'
import _ from 'lodash'
import { Tooltip, Modal as AntdModal } from 'antd'
import Modal from 'react-modal'

import React from 'react'
import Spreadsheet from 'react-spreadsheet'
import { render, unmountComponentAtNode } from 'react-dom'
import { createCentrifuge, subscribe } from '../centrifugo/client'
import i18n from '../i18n'
import moment from 'moment'

import './list.scss'

// Enable Bootstrap tooltips
// https://getbootstrap.com/docs/3.4/javascript/#tooltips
$('[data-toggle="tooltip"]').tooltip();

// Make delivery list rows clickable (navigate to the edit/view link),
// while keeping inner interactive elements (links, buttons, modals…) working.
document.addEventListener('click', function(e) {
  if (e.target.closest('a, button, input, label, [role="button"], .modal')) {
    return
  }
  const row = e.target.closest('tr[data-testid="delivery__list_item"][data-href]')
  if (row) {
    window.location.href = row.getAttribute('data-href')
  }
});

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
        after: startEl.value,
        before: endEl.value
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

function SpreadsheetViewer({ data, errors, status, onClose }) {

  function closeModal(e) {
    e.preventDefault()
    onClose()
  }

  const errorsByRow = _.keyBy(errors, error => error.row)
  const rowsWithErrors = _.keys(errorsByRow)

  return (
    <Modal
      isOpen={ true }
      className="ReactModal__Content--delivery-import-spreadsheet"
      shouldCloseOnOverlayClick={ true }
      onRequestClose={ onClose }
    >
      <header className="delivery-import-spreadsheet__header text-right"><a href="#" onClick={ closeModal }><i className="fa fa-close fa-2x text-muted" /></a></header>
      <div className="delivery-import-spreadsheet__body">
      { status === 'loading' &&
      <div className="delivery-import-spreadsheet__placeholder text-muted">
        <i className="fa fa-spinner fa-spin fa-3x mb-3" />
        <span>{ _t('LOADING') }</span>
      </div>
      }
      { status === 'failed' &&
      <div className="delivery-import-spreadsheet__placeholder text-danger">
        <i className="fa fa-exclamation-triangle fa-3x mb-3" />
        <span>{ _t('SOMETHING_WENT_WRONG') }</span>
      </div>
      }
      { status === 'loaded' &&
      <Spreadsheet
      data={ data }
      // https://github.com/iddan/react-spreadsheet/blob/5751105cd84db1bb745fa37d399ebedcb48220df/src/RowIndicator.tsx
      RowIndicator={ ({ selected, row }) => {

        const hasErrors = rowsWithErrors.includes('' + row)

        const style = hasErrors ? {
          top: 5,
          position: "absolute",
          right: 15,
          width: 0,
          height: 0,
        } : {}

        return (
          <th
            className={ clsx('Spreadsheet__header', {
              'Spreadsheet__header--selected': selected,
            })}
            tabIndex={ 0 }
          >
            <span style={{ display: 'inline-block', width: '100%', position: 'relative' }}>
              { hasErrors &&
              <Tooltip title={ errorsByRow[row].errors.join('\n') } color="red">
                <a className="text-danger fa fa-exclamation-triangle" style={ style } />
              </Tooltip>
              }
              <span>{ row + 1 }</span>
            </span>
          </th>
        );
      } }
      />
      }
      </div>
    </Modal>
  )
}

function renderSpreadsheetViewer({ status, data, errors }) {
  const container = document.getElementById('delivery_import_spreadsheet')

  render(<SpreadsheetViewer
    status={ status }
    data={ data }
    errors={ errors }
    onClose={ () => unmountComponentAtNode(container) } />, container)
}

const importStatusIcon = {
  // Uploaded, waiting for a dispatcher to say which field each of its columns holds
  'mapping': 'hand-pointer-o',
  'pending': 'clock-o',
  'started': 'play',
  'saving': 'floppy-o',
  'completed': 'check',
  'failed': 'exclamation-triangle',
  'cancelled': 'ban',
  // Once the import is over and what it created was cancelled by a dispatcher: nothing from the file is left either
  'items_cancelling': 'spinner fa-spin',
  'items_cancelled': 'ban',
}

// Kept in step with ImportQueue::CANCELLABLE_STATUSES
const cancellableImportStatuses = [ 'mapping', 'pending', 'started' ]

// The values are only written with textContent, there is nothing to escape
const _t = (key, options = {}) => i18n.t(key, { ...options, interpolation: { escapeValue: false } })

function _importProgressText({ status, startedAt, totalRows, processedRows, errorRows, ...cancellation }) {
  // Nothing of the file has been read yet, and nothing will be until its columns are mapped
  if (status === 'mapping') {
    return _t('DELIVERY_IMPORT_WAITING_FOR_MAPPING')
  }

  // Unknown until a worker has read the file
  if (!totalRows) {
    return ''
  }

  const parts = [
    _t('DELIVERY_IMPORT_PROGRESS_ROWS', {
      processed: processedRows.toLocaleString(),
      total: totalRows.toLocaleString(),
      percent: Math.floor(processedRows / totalRows * 100),
    })
  ]

  if (errorRows > 0) {
    parts.push(_t('DELIVERY_IMPORT_PROGRESS_ERRORS', { count: errorRows }))
  }

  if (status === 'saving') {
    parts.push(_t('DELIVERY_IMPORT_PROGRESS_SAVING'))
  }

  if (status === 'started' && startedAt && processedRows > 0 && processedRows < totalRows) {
    const elapsedSeconds = moment().diff(moment(startedAt), 'seconds')
    const remainingSeconds = elapsedSeconds / processedRows * (totalRows - processedRows)

    parts.push(_t('DELIVERY_IMPORT_PROGRESS_REMAINING', {
      duration: moment.duration(remainingSeconds, 'seconds').humanize()
    }))
  }

  return parts.concat(_itemsCancellationText(cancellation)).join(' · ')
}

function _itemsCancellationText({ itemsCancellation, itemsToCancel, cancelledItems, skippedItems }) {
  if (itemsCancellation === 'cancelling') {
    // Unknown until a worker has looked them up
    if (itemsToCancel === null) {
      return [ _t('DELIVERY_IMPORT_CANCELLING_DELIVERIES') ]
    }

    return [ _t('DELIVERY_IMPORT_CANCELLING_DELIVERIES_PROGRESS', {
      done: (cancelledItems + skippedItems).toLocaleString(),
      total: itemsToCancel.toLocaleString(),
    }) ]
  }

  if (itemsCancellation === 'cancelled') {
    const parts = [ _t('DELIVERY_IMPORT_DELIVERIES_CANCELLED', { count: cancelledItems }) ]
    if (skippedItems > 0) {
      parts.push(_t('DELIVERY_IMPORT_DELIVERIES_LEFT', { count: skippedItems }))
    }

    return parts
  }

  return []
}

// The state of what it created, once that is being cancelled, rather than that of the import
function _displayStatus({ status, itemsCancellation }) {
  return itemsCancellation ? `items_${itemsCancellation}` : status
}

function _renderImportStatus(row, importQueue) {
  // As in the template, an icon can come with a modifier: "spinner fa-spin"
  row.querySelector('[data-delivery-import-status]').className = `fa fa-${importStatusIcon[_displayStatus(importQueue)]}`
}

function _renderImportProgress(row, progress) {
  row.querySelector('[data-delivery-import-progress]').textContent = _importProgressText(progress)
  row.querySelector('[data-delivery-import-cancel-button]')
    .classList.toggle('d-none', !cancellableImportStatuses.includes(progress.status))
  // Only once
  if (progress.itemsCancellation) {
    row.querySelector('[data-delivery-import-cancel-deliveries-button]').classList.add('d-none')
  }
}

function _progressFromDataset(el) {
  const {
    status, startedAt, totalRows, processedRows, errorRows,
    itemsCancellation, itemsToCancel, cancelledItems, skippedItems,
  } = el.dataset

  return {
    status,
    startedAt: startedAt || null,
    totalRows: totalRows ? parseInt(totalRows, 10) : null,
    processedRows: parseInt(processedRows || '0', 10),
    errorRows: parseInt(errorRows || '0', 10),
    itemsCancellation: itemsCancellation || null,
    itemsToCancel: itemsToCancel ? parseInt(itemsToCancel, 10) : null,
    cancelledItems: parseInt(cancelledItems || '0', 10),
    skippedItems: parseInt(skippedItems || '0', 10),
  }
}

function _cancelDeliveriesConfirmContent(filename) {
  return (
    <div>
      <p>{ _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_FILE', { filename }) }</p>
      {/* The admin's styles remove the bullets of a list */}
      <ul style={{ listStyle: 'disc', paddingLeft: 20 }}>
        <li>{ _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_ORDERS') }</li>
        <li>{ _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_ASSIGNED') }</li>
        <li>{ _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_UNDER_WAY') }</li>
      </ul>
      <p><strong>{ _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_NO_UNDO') }</strong></p>
    </div>
  )
}

function addCancelDeliveries(el) {
  el.addEventListener('click', e => {

    e.preventDefault()

    const parentEl = e.target.closest('[data-delivery-import]')
    // Only known on page load: an import completed since shows 0
    const count = parseInt(parentEl.dataset.deliveryImportDeliveries || '0', 10)

    AntdModal.confirm({
      title: count > 0 ?
        _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_TITLE', { count }) : _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_TITLE_UNKNOWN'),
      content: _cancelDeliveriesConfirmContent(parentEl.dataset.deliveryImportFilename),
      okText: _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_OK'),
      okButtonProps: { danger: true },
      cancelText: _t('DELIVERY_IMPORT_CANCEL_DELIVERIES_BACK'),
      width: 520,
      // The dialog stays open, its button loading, until the request is answered
      onOk: () => axios({
        method: 'put',
        url: parentEl.dataset.deliveryImportCancelDeliveries,
        headers: {
          Authorization: `Bearer ${window._auth.jwt}`
        }
      }).then(response => {
        // The worker reports how it goes, this is in case it's busy with something else
        _renderImportStatus(parentEl, response.data)
        _renderImportProgress(parentEl, { ...response.data, startedAt: null })
      }).catch(() => {
        window.alert(_t('SOMETHING_WENT_WRONG'))
      }),
    })
  }, false)
}

function addCancel(el) {
  el.addEventListener('click', e => {

    e.preventDefault()

    if (!window.confirm(_t('DELIVERY_IMPORT_CANCEL_CONFIRM'))) {
      return
    }

    const parentEl = e.target.closest('[data-delivery-import]')

    axios({
      method: 'put',
      url: parentEl.dataset.deliveryImportCancel,
      headers: {
        Authorization: `Bearer ${window._auth.jwt}`
      }
    }).then(response => {
      // The live update is sent from this request, through the async transport:
      // it may wait behind the very import the worker is busy with
      _renderImportStatus(parentEl, response.data)
      _renderImportProgress(parentEl, {
        status: response.data.status,
        startedAt: null,
        totalRows: response.data.totalRows,
        processedRows: response.data.processedRows,
        errorRows: response.data.errorRows,
      })
    }).catch(() => {
      window.alert(_t('SOMETHING_WENT_WRONG'))
    })
  }, false)
}

function addSpreadsheetView(viewIcon) {
  viewIcon.addEventListener('click', e => {

    const parentEl = e.target.closest('[data-delivery-import]')

    e.preventDefault()

    // The file and its errors take a few seconds to come back: the modal opens right away,
    // on a spinner, and is filled in once they have
    renderSpreadsheetViewer({ status: 'loading' })

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

      renderSpreadsheetViewer({ status: 'loaded', data, errors })
    }).catch(() => {
      renderSpreadsheetViewer({ status: 'failed' })
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

  const centrifuge = createCentrifuge(deliveryImports.dataset.centrifugoToken)

  subscribe(centrifuge, deliveryImports.dataset.centrifugoChannel, function(data) {
    const { event } = data
    if (event.name === 'import:updated') {
      const row = document.querySelector(`[data-delivery-import-filename="${event.data.filename}"]`)
      // An import from another day than the one displayed
      if (!row) {
        return
      }
      _renderImportProgress(row, event.data)
      _renderImportStatus(row, event.data)
      // The number of deliveries is only known on reload, but a completed import has some
      if (event.data.status === 'completed' && !event.data.itemsCancellation) {
        row.querySelector('[data-delivery-import-cancel-deliveries-button]').classList.remove('d-none')
      }
      if (event.data.status === 'failed') {
        row.classList.add('danger')
        // Its click listener was added with the page: adding it again would open the file twice
        const viewLink = row.querySelector('[data-delivery-import-view]')
        if (viewLink.dataset.errorsLabel) {
          viewLink.textContent = viewLink.dataset.errorsLabel
          viewLink.classList.add('text-danger')
        }
      }
    }
  })

  centrifuge.connect()

  Modal.setAppElement('#delivery_import_spreadsheet')

  deliveryImports.querySelectorAll('[data-delivery-import]').forEach(el => {
    addSpreadsheetView(el.querySelector('[data-delivery-import-view]'))
    addRedownload(el.querySelector('[data-delivery-import-redownload]'))
    addCancel(el.querySelector('[data-delivery-import-cancel-button]'))
    addCancelDeliveries(el.querySelector('[data-delivery-import-cancel-deliveries-button]'))
    _renderImportProgress(el, _progressFromDataset(el.querySelector('[data-delivery-import-progress]')))
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
