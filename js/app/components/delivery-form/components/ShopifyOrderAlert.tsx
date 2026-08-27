import React from 'react';
import { Alert } from 'antd';
import { ShopOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';

export type ShopifyOrder = {
  /** Display name of the order in Shopify, e.g. "#1001". */
  name: string;
  /** Deep link to the order in the merchant's Shopify admin. */
  url: string;
};

type Props = {
  shopifyOrder?: ShopifyOrder | null;
};

/**
 * A delivery created from a Shopify order is otherwise indistinguishable from
 * one created by hand, which makes it hard to reconcile with the merchant's
 * shop. Flag the origin and link straight to the order.
 */
export default function ShopifyOrderAlert({ shopifyOrder }: Props) {
  const { t } = useTranslation();

  if (!shopifyOrder) {
    return null;
  }

  return (
    <Alert
      type="info"
      showIcon
      icon={<ShopOutlined />}
      className="mb-3"
      data-testid="shopify-order-alert"
      message={t('DELIVERY_FORM_SHOPIFY_ORIGIN')}
      description={
        <a href={shopifyOrder.url} target="_blank" rel="noopener noreferrer">
          {t('DELIVERY_FORM_SHOPIFY_VIEW_ORDER', { name: shopifyOrder.name })}
        </a>
      }
    />
  );
}
