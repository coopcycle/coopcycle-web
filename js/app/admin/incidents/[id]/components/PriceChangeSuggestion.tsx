import React from 'react';
import { Alert, Col, Row, Spin } from 'antd';
import { useTranslation } from 'react-i18next';
import { money } from '../../utils';
import { TotalPrice } from '../../../../components/delivery-form/components/order/TotalPrice';
import { Order, OrderItem } from '../../../../api/types';
import Cart from '../../../../components/delivery-form/components/order/Cart';

type Props = {
  isLoading: boolean;
  error: unknown;
  existingOrder: Order;
  suggestedOrder: Order | null;
  suggestionPriceDiff?: number;
  diff: OrderItem[][] | null;
};

export function PriceChangeSuggestion({
  isLoading,
  error,
  suggestionPriceDiff,
  existingOrder,
  suggestedOrder,
  diff,
}: Props) {
  const { t } = useTranslation();

  if (isLoading) {
    return <Spin />;
  }

  if (error || !suggestedOrder || !diff || suggestionPriceDiff === undefined) {
    return (
      <Alert message={t('INCIDENTS_ERROR_LOADING_SUGGESTED_PRICE_CHANGE')} type="error" />
    );
  }

  return (
    <>
      <Row>
        <Col span={24}>
          <h3 data-testid="suggestion-price-change">
            {t('INCIDENTS_SUGGESTED_PRICE_CHANGE', {
              diff:
                (suggestionPriceDiff > 0 ? '+' : '') +
                money(suggestionPriceDiff),
            })}
          </h3>
        </Col>
      </Row>
      <Row gutter={16}>
        <Col span={12}>
          <h4>{t('INCIDENTS_OLD_PRICE')}</h4>
        </Col>
        <Col span={12}>
          <h4>{t('INCIDENTS_NEW_PRICE')}</h4>
        </Col>
      </Row>
      <Row gutter={16}>
        <Col span={12} data-testid="suggestion-old-price-value">
          <TotalPrice
            overridePrice={true}
            total={existingOrder.total}
            taxTotal={existingOrder.taxTotal}
          />
        </Col>
        <Col span={12} data-testid="suggestion-new-price-value">
          <TotalPrice
            overridePrice={false}
            total={suggestedOrder.total}
            taxTotal={suggestedOrder.taxTotal}
          />
        </Col>
      </Row>
      <Row>
        <Col span={24}>
          <h4>{t('INCIDENTS_SUGGESTED_MODIFICATIONS')}</h4>
        </Col>
      </Row>
      <Row gutter={16}>
        <Col span={12} data-testid="suggestion-old-items">
          <Cart orderItems={diff[0]} overridePrice={true} />
        </Col>
        <Col span={12} data-testid="suggestion-new-items">
          <Cart orderItems={diff[1]} overridePrice={false} />
        </Col>
      </Row>
    </>
  );
}
