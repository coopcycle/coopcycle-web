import React, { useMemo } from 'react';
import { TreeSelect } from 'antd';
import {
  useGetPackageSetsQuery,
  useGetPackagesQuery,
} from '../../../../api/slice';
import PickerIsLoading from './PickerIsLoading';
import PickerIsError from './PickerIsError';
import _ from 'lodash';

type TreeNode = {
  value: string;
  title: string;
  selectable?: boolean;
  children?: TreeNode[];
};

const NO_VALUE = '-';

type Props = {
  value: string;
  onChange: (event: { target: { value: string } }) => void;
};

export default function PackagePicker({ value, onChange }: Props) {
  const { data: packageSets, isFetching: isFetchingPackageSets } =
    useGetPackageSetsQuery();
  const { data: packages, isFetching: isFetchingPackages } =
    useGetPackagesQuery();

  const treeData = useMemo<TreeNode[]>(() => {
    if (!packages || !packageSets) {
      return [];
    }

    const packagesBySet = _.groupBy(packages, 'packageSet');

    const treeNodes: TreeNode[] = [
      {
        value: NO_VALUE,
        title: '-',
      },
    ];

    packageSets.forEach(set => {
      const packages = packagesBySet[set['@id']] ?? [];
      if (packages.length > 0) {
        treeNodes.push({
          value: set['@id'],
          title: set.name,
          selectable: false,
          children: packages.map(pkg => ({
            value: pkg['@id'],
            title: pkg.name,
          })),
        });
      }
    });

    return treeNodes;
  }, [packages, packageSets]);

  const selectedValue = useMemo(() => {
    return packages?.find(pkg => pkg.name === value)?.['@id'] ?? '';
  }, [value, packages]);

  if (isFetchingPackages || isFetchingPackageSets) {
    return <PickerIsLoading />;
  }

  if (!packages || !packageSets) {
    return <PickerIsError />;
  }

  return (
    <TreeSelect
      data-testid="condition-package-select"
      showSearch
      treeNodeFilterProp="title"
      treeDefaultExpandAll
      onChange={value => {
        const name =
          packages.find(pkg => pkg['@id'] === value)?.name ?? NO_VALUE;

        // replicate on change signature of html input until we re-write PricePickerLine component
        onChange({
          target: {
            value: name,
          },
        });
      }}
      value={selectedValue}
      treeData={treeData}
    />
  );
}
