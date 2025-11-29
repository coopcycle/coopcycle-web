import React, { useEffect, useState } from 'react';
import { Button, Input } from 'antd';
import { useTranslation } from 'react-i18next';

import './Packages.scss';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { InputPackage, Package } from '../../../../api/types';

type Props = {
  taskId: string;
  packages: Package[];
};

const Packages = ({ taskId, packages }: Props) => {
  const {
    taskValues,
    taskErrors,
    setFieldValue,
    taskIndex: index,
  } = useDeliveryFormFormikContext({
    taskId: taskId,
  });

  const [packagesPicked, setPackagesPicked] = useState<InputPackage[]>(() => {
    let picked: InputPackage[] = [];

    for (const p of packages) {
      const newPackages: InputPackage = {
        type: p.name,
        quantity: 0,
      };
      picked.push(newPackages);
    }

    const preloadedData = taskValues.packages;

    // Update the initial state with preloaded data if available
    if (preloadedData && preloadedData.length > 0) {
      const newPackagesArray = picked.map(p => {
        const match = preloadedData.find(item => item.type === p.type);
        return match || p;
      });
      picked = newPackagesArray;
    }

    return picked;
  });

  const { t } = useTranslation();

  useEffect(() => {
    // const filteredPackages = packagesPicked.filter(p => p.quantity > 0)
    // if (filteredPackages.length > 0) {
    //   setFieldValue(`tasks[${index}].packages`, filteredPackages)
    // }
    setFieldValue(`tasks[${index}].packages`, packagesPicked);
  }, [packagesPicked, setFieldValue, index]);

  const handlePlusButton = (item: Package) => {
    const pack = packagesPicked.find(p => p.type === item.name);
    const index = packagesPicked.findIndex(p => p === pack);
    if (index !== -1 && pack) {
      const newPackagesPicked = [...packagesPicked];
      const newQuantity = pack.quantity + 1;
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: newQuantity,
      };
      setPackagesPicked(newPackagesPicked);
    }
  };

  const handleMinusButton = (item: Package) => {
    const pack = packagesPicked.find(p => p.type === item.name);
    const index = packagesPicked.findIndex(p => p === pack);

    if (index !== -1 && pack) {
      const newPackagesPicked = [...packagesPicked];
      const newQuantity = pack.quantity > 0 ? pack.quantity - 1 : 0;
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: newQuantity,
      };

      setPackagesPicked(newPackagesPicked);
    }
  };

  /**Used to make the input a controlated field */
  const getPackagesItems = (item: Package): number => {
    const sameTypePackage = packagesPicked.find(p => p.type === item.name);
    return sameTypePackage ? sameTypePackage.quantity : 0;
  };

  return (
    <>
      <div className="mb-2 font-weight-bold">{t('DELIVERY_FORM_PACKAGES')}</div>
      {packages.map(item => (
        <div
          key={item['@id']}
          className="packages-item mb-2"
          data-testid={item['@id']}>
          <div className="packages-item__quantity ">
            <Button
              className="packages-item__quantity__button"
              onClick={() => handleMinusButton(item)}>
              -
            </Button>

            <Input
              className="packages-item__quantity__input text-center"
              value={getPackagesItems(item)}
              style={
                getPackagesItems(item) !== 0 ? { fontWeight: '700' } : null
              }
              onChange={e => {
                const packageIndex = packagesPicked.findIndex(
                  p => p.type === item.name,
                );
                const newPackagesPicked = [...packagesPicked];
                newPackagesPicked[packageIndex] = {
                  type: item.name,
                  quantity: e.target.value,
                };
                setPackagesPicked(newPackagesPicked);
              }}
            />

            <Button
              className="packages-item__quantity__button"
              onClick={() => {
                handlePlusButton(item);
              }}>
              +
            </Button>
          </div>
          <span className="packages-item__name border pl-3 pt-1 pb-1">
            {item.name}
          </span>
        </div>
      ))}
      {taskErrors?.packages && (
        <div className="text-danger">{taskErrors.packages}</div>
      )}
    </>
  );
};

export default Packages;
