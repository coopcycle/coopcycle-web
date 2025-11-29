import React from 'react';
import { Select } from 'antd';
import { useGetTimeSlotsQuery } from '../../../../api/slice';
import PickerIsLoading from './PickerIsLoading';
import PickerIsError from './PickerIsError';

type Props = {
  value: string;
  onChange: (event: { target: { value: string } }) => void;
};

export default function TimeSlotPicker({ value, onChange }: Props) {
  const { data: timeSlots, isFetching } = useGetTimeSlotsQuery();

  if (isFetching) {
    return <PickerIsLoading />;
  }

  if (!timeSlots) {
    return <PickerIsError />;
  }

  return (
    <Select
      data-testid="condition-time-slot-select"
      showSearch
      optionFilterProp="label"
      onChange={value =>
        // replicate on change signature of html input until we re-write PricePickerLine component
        onChange({
          target: {
            value: value,
          },
        })
      }
      value={value}
      options={[
        { value: '', label: '-' },
        ...timeSlots.map(item => ({
          value: item['@id'],
          label: item.name,
        })),
      ]}
    />
  );
}
