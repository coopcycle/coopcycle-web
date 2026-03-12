import React, { useEffect, useState } from 'react';
import {
  Button,
  Flex,
  Form,
  InputNumber,
  Radio,
  Skeleton,
  Table,
  Space,
} from 'antd';
import type { TableProps } from 'antd'
import '../Style.scss';
import { useTranslation } from 'react-i18next';
import moment from 'moment'

import PaymentMethodIcon from '../../../../../components/Payment/PaymentMethodIcon';

interface DataType {
  key: string;
  amount: number;
  paymentMethod: string;
  updatedAt: string;
}

const columns: TableProps<DataType>['columns'] = [
  {
    title: 'Amount',
    dataIndex: 'amount',
    key: 'amount',
    render: (amount) => (amount / 100).formatMoney()
  },
  {
    title: 'Payment method',
    dataIndex: 'method',
    key: 'paymentMethod',
    render: (method) => (
      <span title={method.code}>
        <PaymentMethodIcon code={method.code} height={24} />
      </span>
    )
  },
  {
    title: 'Updated at',
    dataIndex: 'updatedAt',
    key: 'updatedAt',
    render: (updatedAt) => moment(updatedAt).format('l LT')
  },

];

export default function ({ order }) {

  const { t } = useTranslation();
  const [ payments, setPayments ] = useState([])

  useEffect(() => {

    async function fetchData() {
      const httpClient = new window._auth.httpClient();
      const { response, error } = await httpClient.get(order['@id'] + '/payments');
      setPayments(response['hydra:member']);
    }

    fetchData()

  }, [order])

  if (payments.length === 0) {
    return <Skeleton />
  }

  const dataSource = payments.map((p, index) => ({ ...p, key: `payment-${index}` }))

  return (
    <>
      <Form
        layout="inline"
        initialValues={{
          liableParty: 'merchant',
        }}
        onValuesChange={(values) => {
          console.log(values)
        }}
        autoComplete="off">
        <Form.Item name="liableParty" label={t('LIABLE_PARTY')} help={t('LIABLE_PART_HELP')}>
          <Radio.Group>
            <Radio value="merchant">{t('LIABLE_PARTY_MERCHANT')}</Radio>
            <Radio value="platform">{t('LIABLE_PARTY_PLATFORM')}</Radio>
          </Radio.Group>
        </Form.Item>
      </Form>
      <hr />
      <div className="mb-4">
        <Table<DataType>
        size="small"
        columns={columns}
        dataSource={dataSource}
        pagination={false}
        showHeader={false}
        expandable={{
          expandedRowRender: (payment) => (
            <Form
              layout="vertical"
              name={`refund-payment-${payment['@id']}`}
              initialValues={{
                amount: payment.amount,
              }}
              onFinish={async values => {
                console.log('onFinish', values)
              }}
              autoComplete="off">
              <Form.Item name="amount"
                label={t('REFUND_AMOUNT')}
                help={payment.supportsPartialRefunds ? '' : t('PARTIAL_REFUNDS_NOT_SUPPORTED')}>
                <InputNumber
                  min={0}
                  max={order.total}
                  parser={(value) => value * 100}
                  formatter={(value) => value / 100}
                  step={50}
                  disabled={!payment.supportsPartialRefunds} />
              </Form.Item>
              <Form.Item label={null}>
                <Button type="primary" htmlType="submit" disabled={!payment.supportsPartialRefunds}>
                  {t('REFUND')}
                </Button>
              </Form.Item>
            </Form>
          ),
          rowExpandable: (record) => true,
        }}
        />
      </div>
    </>
  )
}
