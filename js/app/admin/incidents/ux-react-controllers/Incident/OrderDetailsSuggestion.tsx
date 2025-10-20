import React, { useEffect, useMemo } from 'react';
import { useSelector } from 'react-redux';
import { Alert, Card, Col, Flex, Row, Spin } from 'antd';
import Cart from '../../../../components/delivery-form/components/order/Cart';
import { useCalculatePriceMutation } from '../../../../api/slice';
import { selectStoreUri } from './redux/incidentSlice';
import {
  Adjustment,
  AdjustmentType,
  IncidentMetadataSuggestion,
  Order,
  OrderItem,
} from '../../../../api/types';
import { TotalPrice } from '../../../../components/delivery-form/components/order/TotalPrice';

function areAdjustmentsEqual(
  adj1: Record<AdjustmentType, Adjustment[]>,
  adj2: Record<AdjustmentType, Adjustment[]>,
): boolean {
  const keys1 = (Object.keys(adj1) as AdjustmentType[]).sort();
  const keys2 = (Object.keys(adj2) as AdjustmentType[]).sort();

  if (keys1.length !== keys2.length) return false;
  if (keys1.join(',') !== keys2.join(',')) return false;

  for (const key of keys1) {
    const arr1 = adj1[key];
    const arr2 = adj2[key];

    if (arr1.length !== arr2.length) return false;

    for (let i = 0; i < arr1.length; i++) {
      if (
        arr1[i].label !== arr2[i].label ||
        arr1[i].amount !== arr2[i].amount
      ) {
        return false;
      }
    }
  }

  return true;
}

function areItemsEqual(item1: OrderItem, item2: OrderItem): boolean {
  return (
    item1.total === item2.total &&
    areAdjustmentsEqual(item1.adjustments, item2.adjustments)
  );
}

function removeDuplicateItems(
  arr: OrderItem[],
  lookupItems: OrderItem[],
): OrderItem[] {
  return arr.filter(
    item => !lookupItems.some(lookupItem => areItemsEqual(lookupItem, item)),
  );
}

type Props = {
  existingOrder: Order;
  suggestion: IncidentMetadataSuggestion;
};

export const OrderDetailsSuggestion = ({
  existingOrder,
  suggestion,
}: Props) => {
  const storeUri = useSelector(selectStoreUri);

  const [calculatePrice, { data: calculatePriceData, error, isLoading }] =
    useCalculatePriceMutation();

  const suggestedOrder = useMemo(() => {
    if (!calculatePriceData?.order) {
      return null;
    }

    return calculatePriceData?.order;
  }, [calculatePriceData?.order]);

  const diff = useMemo(() => {
    if (!suggestedOrder?.items) {
      return null;
    }

    return [
      removeDuplicateItems(existingOrder.items, suggestedOrder.items),
      removeDuplicateItems(suggestedOrder.items, existingOrder.items),
    ];
  }, [existingOrder.items, suggestedOrder?.items]);

  useEffect(() => {
    // currently we expect all task data and supplements to be in the suggestion,
    // even when they are unchanged
    const suggestedTasks = suggestion?.tasks;
    const suggestedOrderData = suggestion?.order;

    if (!storeUri) {
      return;
    }

    if (!suggestedTasks) {
      return;
    }

    calculatePrice({
      store: storeUri,
      tasks: suggestedTasks,
      order: suggestedOrderData,
    });
  }, [storeUri, suggestion, calculatePrice]);

  if (isLoading) {
    return <Spin />;
  }

  if (error || !suggestedOrder || !diff) {
    return <Alert message="TODO: Error Text" type="error" />;
  }

  return (
    <Flex vertical gap="middle">
      <Row gutter={16}>
        <Col span={12}>
          <Card title="TODO: Affected order items">
            <Cart orderItems={diff[0]} overridePrice={false} />
          </Card>
        </Col>
        <Col span={12}>
          <Card title="TODO: Suggested modifications">
            <Cart orderItems={diff[1]} overridePrice={false} />
          </Card>
        </Col>
      </Row>
      <Row gutter={16}>
        <Col span={12}>
          <TotalPrice
            overridePrice={false}
            total={existingOrder.total}
            taxTotal={existingOrder.taxTotal}
          />
        </Col>
        <Col span={12}>
          <TotalPrice
            overridePrice={false}
            total={suggestedOrder.total}
            taxTotal={suggestedOrder.taxTotal}
          />
        </Col>
      </Row>
    </Flex>
  );
};
