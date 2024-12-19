import React, { useMemo, useState } from 'react'
import Modal from 'react-modal'

import { useTranslation } from 'react-i18next'
import { InputNumber, DatePicker, Table } from 'antd'
import { useLazyGetOrdersQuery } from '../../api/slice'
import Button from '../core/Button'
import { money } from '../../utils/format'
import { moment } from '../../../shared'

export default () => {
  const [storeId, setStoreId] = useState(null)
  const [dateRange, setDateRange] = useState(null)

  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [trigger, { isLoading, data }] = useLazyGetOrdersQuery()

  const { t } = useTranslation()

  const [isModalOpen, setModalOpen] = useState(false)

  const { dataSource, total } = useMemo(() => {
    if (!data) {
      return { datasource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(order => ({
        ...order,
        key: order['@id'],
        number: order.number,
        date: order.shippingTimeRange
          ? moment(order.shippingTimeRange[1]).format('l')
          : '?',
        description: order.description,
        subTotal: money(order.total - order.taxTotal),
        taxTotal: money(order.taxTotal),
        total: money(order.total),
      })),
      total: data['hydra:totalItems'],
    }
  }, [data])

  const columns = [
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_ORDER_NUMBER_LABEL'),
      dataIndex: 'number',
      key: 'number',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_DATE_LABEL'),
      dataIndex: 'date',
      key: 'date',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_DESCRIPTION_LABEL'),
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_SUB_TOTAL_LABEL'),
      dataIndex: 'subTotal',
      key: 'subTotal',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_TAXES_LABEL'),
      dataIndex: 'taxTotal',
      key: 'taxTotal',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_TOTAL_LABEL'),
      dataIndex: 'total',
      key: 'total',
    },
    {
      key: 'action',
      dataIndex: 'id',
      align: 'right',
      render: id => (
        <a
          href={window.Routing.generate('admin_order_edit', { id })}
          target="_blank"
          rel="noopener noreferrer">
          {t('EDIT')}
        </a>
      ),
    },
  ]

  const reloadData = (page, pageSize) => {
    trigger({
      storeId,
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ['new', 'accepted', 'fulfilled'],
      page: page,
      pageSize: pageSize,
    })
  }

  return (
    <div>
      <h3>{t('ADMIN_ORDERS_TO_INVOICE_TITLE')}</h3>
      <div>
        {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STORE')}
        <InputNumber min={0} onChange={setStoreId} />
        {t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE')}
        <DatePicker.RangePicker onChange={setDateRange} />
        <Button
          primary
          onClick={() => {
            if (!storeId) {
              return
            }

            if (!dateRange) {
              return
            }

            reloadData(currentPage, pageSize)
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_REFRESH')}
        </Button>
      </div>
      <div className="row">
        <div className="col-md-12">
          <Table
            columns={columns}
            loading={isLoading}
            dataSource={dataSource}
            rowKey="@id"
            pagination={{
              pageSize,
              total,
            }}
            onChange={pagination => {
              reloadData(pagination.current, pagination.pageSize)

              setCurrentPage(pagination.current)
              setPageSize(pagination.pageSize)
            }}
          />
        </div>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('warehouse')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{ content: { overflow: 'unset' } }}>
        <div className="modal-header">
          <h4 className="modal-title">
            {t('ADMIN_WAREHOUSE_FORM_TITLE')}
            <a className="pull-right" onClick={() => setModalOpen(false)}>
              <i className="fa fa-close"></i>
            </a>
          </h4>
        </div>
        {/*<WarehouseForm initialValues={initialValues} onSubmit={onSubmit}/>*/}
      </Modal>
    </div>
  )
}
