import React, { useEffect, useMemo } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Alert, App, Button, Col, Flex, Row, Spin } from 'antd';
import Cart from '../../../../components/delivery-form/components/order/Cart';
import {
  useCalculatePriceMutation,
  useIncidentActionMutation,
} from '../../../../api/slice';
import {
  selectEvents,
  selectIncident,
  selectStoreUri,
  setEvents,
} from './redux/incidentSlice';
import {
  Adjustment,
  AdjustmentType,
  IncidentMetadataSuggestion,
  Order,
  OrderItem,
} from '../../../../api/types';

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

export const ResolveSuggestion = ({ existingOrder, suggestion }: Props) => {
  const storeUri = useSelector(selectStoreUri);
  const incident = useSelector(selectIncident);

  const dispatch = useDispatch();

  const { notification } = App.useApp();

  const [calculatePrice, { data: calculatePriceData, error, isLoading }] =
    useCalculatePriceMutation();

  const [
    incidentAction,
    {
      isLoading: isActionLoading,
      isSuccess: isActionSuccess,
      isError: isActionError,
      data: actionData,
    },
  ] = useIncidentActionMutation();

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
    if (!storeUri) {
      return;
    }

    if (!suggestion) {
      return;
    }

    // currently we expect all task data and supplements to be in the suggestion,
    // even when they are unchanged
    const suggestedTasks = suggestion.tasks;
    const suggestedOrderData = suggestion.order;

    if (!suggestedTasks) {
      return;
    }

    calculatePrice({
      store: storeUri,
      id: suggestion.id,
      tasks: suggestedTasks,
      order: suggestedOrderData,
    });
  }, [storeUri, suggestion, calculatePrice]);

  useEffect(() => {
    if (isActionSuccess) {
      dispatch(setEvents(actionData.events));

      notification.success({
        message: 'Action completed successfully',
      });
    }
    if (isActionError) {
      notification.error({
        message: 'Failed to perform action',
      });
    }
  }, [isActionSuccess, isActionError, notification]);

  const handleAcceptSuggestion = async () => {
    if (!incident?.id) return;

    await incidentAction({
      incidentId: incident.id,
      action: 'accepted_suggestion',
    });
  };

  const handleRejectSuggestion = async () => {
    if (!incident?.id) return;

    await incidentAction({
      incidentId: incident.id,
      action: 'rejected_suggestion',
    });
  };

  if (isLoading) {
    return <Spin />;
  }

  if (error || !suggestedOrder || !diff) {
    return <Alert message="TODO: Error Text" type="error" />;
  }

  const isButtonDisabled = isActionLoading;

  return (
    <Flex vertical gap="middle">
      <Row gutter={16}>
        <Col span={12}>
          <h4>TODO: Affected order items:</h4>
          <Cart orderItems={diff[0]} overridePrice={false} />
        </Col>
        <Col span={12}>
          <h4>TODO: Suggested modifications:</h4>
          <Cart orderItems={diff[1]} overridePrice={false} />
        </Col>
      </Row>
      <Row gutter={16}>
        <Col span={12}>
          <Button
            danger
            block
            onClick={handleRejectSuggestion}
            disabled={isButtonDisabled}
            loading={isActionLoading}>
            TODO: Refuse suggestions
          </Button>
        </Col>
        <Col span={12}>
          <Button
            type="primary"
            block
            onClick={handleAcceptSuggestion}
            disabled={isButtonDisabled}
            loading={isActionLoading}>
            TODO: Apply suggestions
          </Button>
        </Col>
      </Row>
    </Flex>
  );
};

export const OrderDetailsSuggestion = ({
  existingOrder,
  suggestion,
}: Props) => {
  const events = useSelector(selectEvents);

  if (
    events.some(
      event =>
        event.type === 'accepted_suggestion' ||
        event.type === 'rejected_suggestion',
    )
  ) {
    return <div>TODO: incident already handled</div>;
  } else {
    return (
      <ResolveSuggestion
        existingOrder={existingOrder}
        suggestion={suggestion}
      />
    );
  }
};
