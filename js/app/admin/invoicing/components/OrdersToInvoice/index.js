import React, { useMemo, useState } from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { InputNumber, DatePicker, Table } from 'antd'

import { useLazyGetInvoiceLineItemsQuery } from '../../../../api/slice'
import { money } from '../../../../utils/format'
import { moment } from '../../../../../shared'
import Button from '../../../../components/core/Button'
import { prepareParams } from '../../redux/actions'
import ExportModalContent from '../ExportModalContent'

export default () => {
  const [storeId, setStoreId] = useState(null)
  const [dateRange, setDateRange] = useState(null)

  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [isModalOpen, setModalOpen] = useState(false)

  const [trigger, { isFetching, data }] = useLazyGetInvoiceLineItemsQuery()

  const { t } = useTranslation()

  const params = useMemo(() => {
    if (!storeId) {
      return null
    }

    if (!dateRange) {
      return null
    }

    return prepareParams({
      store: [storeId],
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ['new', 'accepted', 'fulfilled'],
    })
  }, [storeId, dateRange])

  const { dataSource, total } = useMemo(() => {
    if (!data) {
      return { datasource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(order => ({
        ...order,
        key: order['@id'],
        number: order.orderNumber,
        date: order.date ? moment(order.date).format('l') : '?',
        description: order.description,
        subTotal: money(order.subTotal),
        tax: money(order.tax),
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
      dataIndex: 'tax',
      key: 'tax',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_TOTAL_LABEL'),
      dataIndex: 'total',
      key: 'total',
    },
    {
      key: 'action',
      dataIndex: 'orderId',
      align: 'right',
      render: orderId => (
        <a
          href={window.Routing.generate('admin_order', { id: orderId })}
          target="_blank"
          rel="noopener noreferrer">
          {t('VIEW')}
        </a>
      ),
    },
  ]

  const reloadData = (page, pageSize) => {
    if (!params) {
      return
    }

    trigger({
      params,
      page: page,
      pageSize: pageSize,
    })
  }

  return (
    // marginTop: 48px: h5 marginTop (10px) + 38px
    <div style={{ marginTop: '38px' }}>
      <h5>{t('ADMIN_ORDERS_TO_INVOICE_TITLE')}</h5>
      <div className="d-flex" style={{ marginTop: '12px', gap: '24px' }}>
        {t('ADMIN_DASHBOARD_NAV_FILTERS')}:
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE')}
          <DatePicker.RangePicker onChange={setDateRange} />
        </div>
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STORE')}
          <InputNumber min={0} onChange={setStoreId} />
        </div>
        <div className="d-flex flex-column justify-content-end">
          <Button
            primary
            onClick={() => {
              reloadData(currentPage, pageSize)
            }}>
            {t('ADMIN_ORDERS_TO_INVOICE_REFRESH')}
          </Button>
        </div>
      </div>
      <Table
        style={{ marginTop: '48px' }}
        columns={columns}
        loading={isFetching}
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
      <div className="d-flex justify-content-end" style={{ marginTop: '24px' }}>
        <Button
          primary
          onClick={() => {
            if (!params) {
              return
            }

            setModalOpen(true)
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('invoicing')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{ content: { overflow: 'unset' } }}>
        <ExportModalContent
          dateRange={dateRange}
          params={params}
          setModalOpen={setModalOpen}
        />
      </Modal>
    </div>
  )
}
