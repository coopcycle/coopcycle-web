import { useEffect, useMemo } from 'react';
import { useSelector } from 'react-redux';
import {
  Adjustment,
  AdjustmentType,
  IncidentMetadataSuggestion,
  Order,
  OrderItem,
} from '../../../../api/types';
import { selectOrder, selectStoreUri } from '../redux/incidentSlice';
import { useCalculatePriceMutation } from '../../../../api/slice';

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
    item1.variant?.name === item2.variant?.name &&
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

export function usePriceChangeSuggestion(
  suggestion: IncidentMetadataSuggestion,
) {
  const storeUri = useSelector(selectStoreUri);
  const existingOrder = useSelector(selectOrder) as Order;

  const [calculatePrice, { data: calculatePriceData, error, isLoading }] =
    useCalculatePriceMutation();

  const suggestedOrder = useMemo(() => {
    if (!calculatePriceData?.order) {
      return null;
    }

    return calculatePriceData?.order;
  }, [calculatePriceData?.order]);

  const suggestionPriceDiff = useMemo(() => {
    if (!suggestedOrder?.total) {
      return undefined;
    }

    return suggestedOrder.total - existingOrder.total;
  }, [suggestedOrder?.total, existingOrder.total]);

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

  return {
    isLoading,
    error,
    existingOrder,
    suggestedOrder,
    suggestionPriceDiff,
    diff,
  };
}
