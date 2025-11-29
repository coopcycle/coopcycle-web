import React, { useMemo } from 'react';
import { Select } from 'antd';
import { useGetPackagesQuery } from '../../../../api/slice';
import PickerIsLoading from './PickerIsLoading';
import PickerIsError from './PickerIsError';

type Props = {
  value: string;
  onChange: (event: { target: { value: string } }) => void;
};

export default function PackageNamePicker({ value, onChange }: Props) {
  const { data: packages, isFetching } = useGetPackagesQuery();

  const packageNames = useMemo(() => {
    if (!packages) {
      return [];
    }

    const allPackageNames: string[] = [];

    packages.forEach(pkg => {
      // ignore packages from deleted package sets
      if (!pkg.packageSet) {
        return;
      }

      allPackageNames.push(pkg.name);
    });

    const uniquePackageNames = Array.from(new Set(allPackageNames)).sort();

    return uniquePackageNames;
  }, [packages]);

  if (isFetching) {
    return <PickerIsLoading />;
  }

  if (!packages) {
    return <PickerIsError />;
  }

  return (
    <Select
      data-testid="condition-package-select"
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
      placeholder={'-'}
      value={value}
      options={packageNames.map(item => ({
        value: item,
        label: item,
      }))}
    />
  );
}
