import React from 'react';
import {
  Form,
  InputNumber,
} from 'antd';
import '../Style.scss';
import { useTranslation } from 'react-i18next';

export default function ({ incident, order, form }) {
  const { t } = useTranslation();

  return (
    <Form
      layout="vertical"
      form={form}
      name="credit-note"
      onFinish={async values => {

        const httpClient = new window._auth.httpClient();

        const { response: promotion, error: createPromotionError } = await httpClient.post(
          order['@id'] + '/credit_notes',
          { amount: values.amount },
        );

        if (createPromotionError) {
          // TODO Show error message
          return;
        }

        const { response: incidentWithMetadata, error: addMetadataError } = await httpClient.post(
          incident['@id'] + '/metadata',
          {
            metadata: [
              {
                credit_note: promotion['@id']
              }
            ]
          },
        );

        if (addMetadataError) {
          // TODO Show error message
          return;
        }

        window.location.reload();

      }}
      autoComplete="off">
      <Form.Item name="amount" label={t('CREDIT_NOTE_AMOUNT')}>
        <InputNumber
          min={0}
          max={order.total}
          defaultValue={order.total}
          parser={(value) => value * 100}
          formatter={(value) => value / 100}
          step={50} />
      </Form.Item>
    </Form>
  );
}
