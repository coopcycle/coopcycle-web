import React, { useEffect, useState, useMemo } from 'react';
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

const PaymentForm = ({ payment, liablePartyForm }) => {

  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);

  return (
    <Form
      layout="vertical"
      name={`refund-payment-${payment['@id']}`}
      initialValues={{
        amount: payment.amount - payment.refundedAmount,
      }}
      onFinish={async (values: object) => {

        const httpClient = new window._auth.httpClient();

        setIsLoading(true)
        const { response, error } = await httpClient.post(
          payment['@id'] + '/refunds',
          {
            ...liablePartyForm.getFieldsValue(),
            ...values
          },
        );
        setIsLoading(false)

        if (!error) {
          window.location.reload();
        }

      }}
      autoComplete="off">
      <Form.Item name="amount"
        label={t('REFUND_AMOUNT')}
        help={payment.supportsPartialRefunds ? '' : t('PARTIAL_REFUNDS_NOT_SUPPORTED')}>
        <InputNumber
          min={0}
          max={payment.amount - payment.refundedAmount}
          parser={(value) => value * 100}
          formatter={(value) => value / 100}
          step={50}
          disabled={!payment.supportsPartialRefunds} />
      </Form.Item>
      <Form.Item label={null}>
        <Button loading={isLoading} type="primary" htmlType="submit" disabled={!payment.supportsPartialRefunds}>
          {t('REFUND')}
        </Button>
      </Form.Item>
    </Form>
  )
}

export default function ({ order, liablePartyForm }) {

  const { t } = useTranslation();
  const [payments, setPayments] = useState([])
  const [isFullRefundButtonLoading, setIsFullRefundButtonLoading] = useState(false);

  const columns: TableProps<DataType>['columns'] = useMemo(() => ([
    {
      title: t('PAYMENT_AMOUNT'),
      dataIndex: 'amount',
      key: 'amount',
      render: (amount) => (amount / 100).formatMoney()
    },
    {
      title: t('PAYMENT_REFUNDED_AMOUNT'),
      dataIndex: 'refundedAmount',
      key: 'refundedAmount',
      render: (amount) => (amount / 100).formatMoney()
    },
    {
      title: t('PAYMENT_METHOD'),
      dataIndex: 'method',
      key: 'paymentMethod',
      render: (method) => (
        <span title={method.code}>
          <PaymentMethodIcon code={method.code} height={24} />
        </span>
      )
    },
    {
      title: t('PAYMENT_UPDATED_AT'),
      dataIndex: 'updatedAt',
      key: 'updatedAt',
      render: (updatedAt) => moment(updatedAt).format('l LT')
    },
  ]))

  useEffect(() => {

    async function fetchData() {
      const httpClient = new window._auth.httpClient();
      const { response, error } = await httpClient.get(order['@id'] + '/payments');
      setPayments(response['hydra:member']);
    }

    fetchData()

  }, [order])

  const isFullRefundAvailable = useMemo(() => {
    const refundedAmountTotal = payments.reduce((total: number, { amount }) => total + amount, 0);
    return refundedAmountTotal === 0
  }, [payments])

  if (payments.length === 0) {
    return <Skeleton />
  }

  const dataSource = payments.map((p, index) => ({ ...p, key: `payment-${index}` }))

  return (
    <>
      <Form
        form={liablePartyForm}
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
        expandable={{
          expandedRowRender: (payment) => <PaymentForm payment={payment} liablePartyForm={liablePartyForm} />,
          rowExpandable: (record) => true,
        }}
        />
      </div>
      <Flex justify="flex-end">
        <Button
          disabled={!isFullRefundAvailable}
          color="danger"
          variant="outlined"
          loading={isFullRefundButtonLoading}
          onClick={async () => {
            const httpClient = new window._auth.httpClient();
            setIsFullRefundButtonLoading(true)
            const { response, error } = await httpClient.put(
              order['@id'] + '/refund',
              liablePartyForm.getFieldsValue(),
            );
            setIsFullRefundButtonLoading(false)
            if (!error) {
              window.location.reload();
            }
          }}
        >
          {t('REFUND_FULL', { amount: (order.total / 100).formatMoney() })}
        </Button>
      </Flex>
    </>
  )
}
