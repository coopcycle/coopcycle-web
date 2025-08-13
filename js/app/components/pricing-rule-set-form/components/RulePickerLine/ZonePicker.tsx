import React from 'react';
import { Select } from 'antd';
import { useGetZonesQuery } from '../../../../api/slice';
import PickerIsLoading from './PickerIsLoading';
import PickerIsError from './PickerIsError';

type Props = {
  value: string;
  onChange: (event: { target: { value: string } }) => void;
};

export default function ZonePicker({ value, onChange }: Props) {
  const { data: zones, isFetching } = useGetZonesQuery();

  if (isFetching) {
    return <PickerIsLoading />;
  }

  if (!zones) {
    return <PickerIsError />;
  }

  return (
    <Select
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
        ...zones.map(item => ({
          value: item.name,
          label: item.name,
        })),
      ]}
    />
  );
}
