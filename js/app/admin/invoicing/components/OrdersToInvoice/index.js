import React, { useMemo, useState } from 'react'
import Modal from 'react-modal'

import { useTranslation } from 'react-i18next'
import { InputNumber, DatePicker, Table } from 'antd'
import { useDispatch } from 'react-redux'

import { useLazyGetInvoiceLineItemsQuery } from '../../../../api/slice'
import { money } from '../../../../utils/format'
import { moment } from '../../../../../shared'
import Button from '../../../../components/core/Button'
import { downloadFile, prepareParams } from '../../redux/actions'

export default () => {
  const [storeId, setStoreId] = useState(null)
  const [dateRange, setDateRange] = useState(null)

  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [isModalOpen, setModalOpen] = useState(false)

  const [trigger, { isLoading, data }] = useLazyGetInvoiceLineItemsQuery()

  const { t } = useTranslation()

  const dispatch = useDispatch()

  const params = useMemo(() => {
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
          href={window.Routing.generate('admin_order_edit', { id: orderId })}
          target="_blank"
          rel="noopener noreferrer">
          {t('EDIT')}
        </a>
      ),
    },
  ]

  const reloadData = (page, pageSize) => {
    trigger({
      params,
      page: page,
      pageSize: pageSize,
    })
  }

  const download = () => {
    const filename = `orders_${dateRange[0].format(
      'YYYY-MM-DD',
    )}_${dateRange[1].format('YYYY-MM-DD')}.csv`

    dispatch(
      downloadFile({
        params,
        filename,
      }),
    )
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
      <Button
        primary
        onClick={() => {
          if (!storeId) {
            return
          }

          if (!dateRange) {
            return
          }

          setModalOpen(true)
        }}>
        {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
      </Button>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('warehouse')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{ content: { overflow: 'unset' } }}>
        <div className="modal-header">
          <h4 className="modal-title">
            {t('ADMIN_DASHBOARD_NAV_EXPORT')}
            <a className="pull-right" onClick={() => setModalOpen(false)}>
              <i className="fa fa-close"></i>
            </a>
          </h4>
        </div>
        <Button
          primary
          onClick={() => {
            download()
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </Modal>
    </div>
  )
}
