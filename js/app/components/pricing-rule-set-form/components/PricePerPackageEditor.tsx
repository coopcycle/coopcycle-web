import React, { useEffect, useMemo, useState } from 'react';
import { getCurrencySymbol } from '../../../i18n';
import { useTranslation } from 'react-i18next';
import { useGetPackagesQuery } from '../../../api/slice';
import PickerIsLoading from './RulePickerLine/PickerIsLoading';
import PickerIsError from './RulePickerLine/PickerIsError';
import PackageNamePicker from './RulePickerLine/PackageNamePicker';

export type PricePerPackageValue = {
  packageName: string;
  unitPrice: number;
  offset: number;
  discountPrice: number;
};

type Props = {
  defaultValue: PricePerPackageValue;
  onChange: (value: PricePerPackageValue) => void;
};

export default ({ defaultValue, onChange }: Props) => {
  const { data: packages, isFetching } = useGetPackagesQuery();

  const packageNames = useMemo(() => {
    if (!packages) {
      return undefined;
    }

    return packages ? packages.map(item => item.name) : [];
  }, [packages]);

  const { t } = useTranslation();

  const [unitPrice, setUnitPrice] = useState(defaultValue.unitPrice || 0);
  const [packageName, setPackageName] = useState(undefined as string | undefined);
  const [offset, setOffset] = useState(defaultValue.offset || 0);
  const [discountPrice, setDiscountPrice] = useState(
    defaultValue.discountPrice || 0,
  );
  const [withDiscount, setWithDiscount] = useState(defaultValue.offset > 0);

  useEffect(() => {
    if (!packageNames) {
      return;
    }

    if (packageNames.length === 0) {
      return;
    }

    setPackageName(defaultValue.packageName || packageNames[0]);
  }, [defaultValue.packageName, packageNames]);

  if (isFetching) {
    return <PickerIsLoading />;
  }

  if (!packages) {
    return <PickerIsError />;
  }

  return (
    <div data-testid="price_rule_price_per_package_editor">
      <div className="d-flex align-items-center">
        <label className="mr-2">
          <input
            type="number"
            defaultValue={unitPrice / 100}
            size="4"
            min="0"
            step=".001"
            className="form-control d-inline-block"
            style={{ width: '80px' }}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
              setUnitPrice(parseFloat(e.target.value) * 100);
              onChange({
                packageName,
                unitPrice: parseFloat(e.target.value) * 100,
                offset,
                discountPrice,
              });
            }}
          />
          <span className="ml-2">{getCurrencySymbol()}</span>
        </label>
        <label className="mr-2">
          <span className="mx-2">{t('PRICE_RANGE_EDITOR.PER_PACKAGE')}</span>
        </label>
        <div className="flex-1">
          <PackageNamePicker
            onChange={(e: { target: { value: string } }) => {
              if (e.target.value) {
                setPackageName(e.target.value);
                onChange({
                  packageName: e.target.value,
                  unitPrice,
                  offset,
                  discountPrice,
                });
              }
            }}
            value={packageName}
          />
        </div>
      </div>
      {withDiscount && (
        <div>
          <label className="mr-2">
            <input
              type="number"
              defaultValue={discountPrice / 100}
              size="4"
              min="0"
              step=".1"
              className="form-control d-inline-block"
              style={{ width: '80px' }}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                setDiscountPrice(parseFloat(e.target.value) * 100);
                onChange({
                  packageName,
                  unitPrice,
                  offset,
                  discountPrice: parseFloat(e.target.value) * 100,
                });
              }}
            />
            <span className="ml-2">{getCurrencySymbol()}</span>
          </label>
          <label className="mr-2">
            <span className="mx-2">
              {t('PRICE_RANGE_EDITOR.PER_PACKAGE_STARTING')}
            </span>
            <input
              type="number"
              defaultValue={offset}
              size="4"
              min="2"
              step="1"
              className="form-control d-inline-block"
              style={{ width: '80px' }}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                setOffset(parseInt(e.target.value, 10));
                onChange({
                  packageName,
                  unitPrice: parseFloat(e.target.value) * 100,
                  offset: parseInt(e.target.value, 10),
                  discountPrice,
                });
              }}
            />
          </label>
          <button
            type="button"
            className="btn btn-xs btn-default"
            onClick={() => {
              setWithDiscount(false);
              setOffset(0);
              onChange({
                packageName,
                unitPrice,
                offset: 0,
                discountPrice,
              });
            }}>
            <i className="fa fa-times mr-1"></i>
            <span>{t('PRICE_RANGE_EDITOR.PER_PACKAGE_DEL_DISCOUNT')}</span>
          </button>
        </div>
      )}
      {!withDiscount && (
        <button
          type="button"
          className="btn btn-xs btn-default"
          onClick={() => {
            setWithDiscount(true);
            setOffset(2);
            setDiscountPrice(100);
            onChange({
              packageName,
              unitPrice,
              offset: 2,
              discountPrice: 100,
            });
          }}>
          <i className="fa fa-plus mr-1"></i>
          <span>{t('PRICE_RANGE_EDITOR.PER_PACKAGE_ADD_DISCOUNT')}</span>
        </button>
      )}
    </div>
  );
};
