import React, { useMemo, useState } from 'react'
import Modal from 'react-modal'

import { useTranslation } from 'react-i18next'
import { InputNumber, DatePicker, Table } from 'antd'
import { useLazyGetOrdersQuery } from '../../api/slice'
import Button from '../core/Button'

export default () => {

  const [ storeId, setStoreId ] = useState(null)
  const [ dateRange, setDateRange ] = useState(null)

  const [ trigger, { isLoading, data } ] = useLazyGetOrdersQuery()

  const { t } = useTranslation()

  const [ isModalOpen, setModalOpen ] = useState(false)

  const dataSource = useMemo(() => {
    if (!data) {
      return undefined
    }

    return data.map((order) => {
      return {
        ...order, //todo: localise
        description: `Order #${ order.number } - Pickup: A; Dropoff: B  - etc. - ${ order.notes }`,
        subTotal: order.total - order.taxTotal,
        shippedAt: order.shippedAt, // deprecated?
        // shippedAt: order.shippingTimeRange[1],
      }
    })
  }, [ data ])

  const columns = [
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_ORDER_NUMBER_LABEL'),
      dataIndex: 'number',
      key: 'number',
    }, {
      title: t('ADMIN_ORDERS_TO_INVOICE_DATE_LABEL'),
      dataIndex: 'shippedAt',
      key: 'shippedAt',
    }, {
      title: t('ADMIN_ORDERS_TO_INVOICE_DESCRIPTION_LABEL'),
      dataIndex: 'description',
      key: 'description',
    }, {
      title: t('ADMIN_ORDERS_TO_INVOICE_SUB_TOTAL_LABEL'),
      dataIndex: 'subTotal',
      key: 'subTotal',
    }, {
      title: t('ADMIN_ORDERS_TO_INVOICE_TAXES_LABEL'),
      dataIndex: 'taxTotal',
      key: 'taxTotal',
    }, {
      title: t('ADMIN_ORDERS_TO_INVOICE_TOTAL_LABEL'),
      dataIndex: 'total',
      key: 'total',
    }, {
      key: 'action', align: 'right', render: (record) => <div>EDIT TODO</div>,
    } ]

  return (<div>
      <h3>{ t('ADMIN_ORDERS_TO_INVOICE_TITLE') }</h3>
      <div>
        { t('ADMIN_ORDERS_TO_INVOICE_FILTER_STORE') }
        <InputNumber min={ 0 } onChange={ setStoreId } />
        { t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE') }
        <DatePicker.RangePicker onChange={ setDateRange } />
        <Button primary onClick={ () => {
          if (!storeId) {
            return
          }

          if (!dateRange) {
            return
          }

          trigger({
            storeId, dateRange: [
              dateRange[0].format('YYYY-MM-DD'),
              dateRange[1].format('YYYY-MM-DD') ],
          })
        } }>{ t('ADMIN_ORDERS_TO_INVOICE_REFRESH') }</Button>
      </div>
      <div className="row pull-right mb-2">
        <div className="col-md-12">
          <a onClick={ () => setModalOpen(true) } className="btn btn-success">
            <i className="fa fa-plus"></i> { t('ADD_BUTTON') }
          </a>
        </div>
      </div>
      <div className="row">
        <div className="col-md-12">
          <Table
            columns={ columns }
            loading={ isLoading }
            dataSource={ dataSource }
            rowKey="@id"
          />
        </div>
      </div>
      <Modal
        isOpen={ isModalOpen }
        appElement={ document.getElementById('warehouse') }
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={ true }
        shouldCloseOnEsc={ true }
        style={ { content: { overflow: 'unset' } } }
      >
        <div className="modal-header">
          <h4 className="modal-title">
            { t('ADMIN_WAREHOUSE_FORM_TITLE') }
            <a className="pull-right" onClick={ () => setModalOpen(false) }><i
              className="fa fa-close"></i></a>
          </h4>
        </div>
        {/*<WarehouseForm initialValues={initialValues} onSubmit={onSubmit}/>*/ }
      </Modal>
    </div>

  )
}
