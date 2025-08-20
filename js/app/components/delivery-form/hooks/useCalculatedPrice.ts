import { useCallback, useEffect, useMemo } from 'react';
import { useCalculatePriceMutation } from '../../../api/slice';
import _ from 'lodash';
import { DeliveryFormValues } from '../types';
import { useDeliveryFormFormikContext } from './useDeliveryFormFormikContext';
import { HydraError, Uri } from '../../../api/types';

type Params = {
  storeUri: Uri;
  skip?: boolean;
};

export const useCalculatedPrice = ({ storeUri, skip = false }: Params) => {
  const { values } = useDeliveryFormFormikContext();

  const [calculatePrice, { data, error, isLoading }] =
    useCalculatePriceMutation();

  const errorData = useMemo(() => {
    if (!error) {
      return undefined;
    }

    // FetchBaseQueryError with serialized HydraError
    if ('data' in error && typeof error.status === 'number') {
      return error.data as HydraError;
    }

    // FetchBaseQueryError
    if ('status' in error) {
      return {
        name: error.status,
        message: error.error,
      } as Error;
    }

    return {
      name: error.name,
      message: error.message,
    } as Error;
  }, [error]);

  const calculatePriceDebounced = useMemo(
    () => _.debounce(calculatePrice, 800),
    [calculatePrice],
  );

  const convertValuesToPayload = useCallback(
    (values: DeliveryFormValues) => {
      const infos = {
        store: storeUri,
        tasks: structuredClone(values.tasks),
        order: structuredClone(values.order),
      };
      return infos;
    },
    [storeUri],
  );

  useEffect(() => {
    if (skip) {
      return;
    }

    // Don't calculate price until all tasks have an address
    if (!values.tasks.every(task => task.address.streetAddress)) {
      return;
    }

    // Don't calculate price if a time slot (timeSlotUrl) is selected, but no choice (timeSlot) is made yet
    if (
      !values.tasks.every(
        task => (task.timeSlotUrl && task.timeSlot) || !task.timeSlotUrl,
      )
    ) {
      return;
    }

    const infos = convertValuesToPayload(values);
    infos.tasks.forEach(task => {
      if (task['@id']) {
        delete task['@id'];
      }
    });

    calculatePriceDebounced(infos);
  }, [skip, values, convertValuesToPayload, calculatePriceDebounced]);

  return {
    data,
    error: errorData,
    isLoading,
  };
};
