import { useCallback, useEffect, useMemo } from 'react';
import { useCalculatePriceMutation } from '../../../api/slice';
import _ from 'lodash';
import { DeliveryFormValues } from '../types';
import { useDeliveryFormFormikContext } from './useDeliveryFormFormikContext';
import { Uri } from '../../../api/types';

type Params = {
  storeUri: Uri;
  skip?: boolean;
};

export const useCalculatedPrice = ({ storeUri, skip = false }: Params) => {
  const { values } = useDeliveryFormFormikContext();

  const [calculatePrice, { data, error, isLoading }] =
    useCalculatePriceMutation();

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
    error: error?.data,
    isLoading,
  };
};
