import React from 'react';
import { Alert, Button } from 'antd';
import { useTranslation } from 'react-i18next';

type Props = {
  orderId: number;
  orderNumber?: string;
};

export function OrderAlert({ orderId, orderNumber }: Props) {
  const { t } = useTranslation();

  const orderWithNumber = t('ORDER_WITH_NUMBER', {
    number: orderNumber ?? `#${orderId}`,
  });

  return (
    <Alert
      message={
        <span>
          {t('ADMIN_DASHBOARD_TASK_FORM_ORDER_ALERT', {
            order: orderWithNumber,
          })}
        </span>
      }
      type="info"
      action={
        <Button
          data-testid="view-order"
          size="small"
          onClick={() =>
            window.open(
              window.Routing.generate('admin_order', {
                id: orderId,
              }),
              '_blank',
            )
          }>
          {t('VIEW')} {orderWithNumber}
        </Button>
      }
    />
  );
}
