import React, { useEffect, useMemo, useState } from 'react';
import { getCurrencySymbol } from '../../../i18n';
import { useTranslation } from 'react-i18next';
import Select from 'react-select';
import _ from 'lodash';
import { useGetPackagesQuery } from '../../../api/slice';
import PickerIsLoading from './RulePickerLine/PickerIsLoading';
import PickerIsError from './RulePickerLine/PickerIsError';

/**
 * Custom styles for the react-select component in order to:
 * - set container display so that it will flow horizontally with its siblings
 * - set a fixed width for the control to avoid it collapsing on itself,
 *     and make sure long labels wrap correctly within the fixed width
 * - inherit the font color from the containing CSS context
 * - remove vertical padding and hide the separator to better match the styling
 *     of other native <select> elements on the page
 */
const reactSelectStyles = {
  container: provided => ({
    ...provided,
    display: 'inline-block',
    width: 150,
  }),
  control: provided => ({ ...provided, minHeight: undefined }),
  valueContainer: provided => ({ ...provided, padding: '0 5px' }),
  placeholder: provided => ({ ...provided, color: undefined }),
  input: provided => ({ ...provided, color: undefined }),
  singleValue: provided => ({ ...provided, color: undefined }),
  indicatorSeparator: () => ({ display: 'none' }),
  dropdownIndicator: provided => ({
    ...provided,
    color: undefined,
    padding: 0,
    ':hover': {
      color: undefined,
    },
  }),
  option: provided => ({ ...provided, wordWrap: 'break-word' }),
};

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
  const [packageName, setPackageName] = useState();
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
      <div>
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
          <Select
            value={{ value: packageName, label: packageName }}
            onChange={(
              selectedOption: { value: string; label: string } | null,
            ) => {
              if (selectedOption) {
                setPackageName(selectedOption.value);
                onChange({
                  packageName: selectedOption.value,
                  unitPrice,
                  offset,
                  discountPrice,
                });
              }
            }}
            options={_.sortBy(packageNames).map(pkg => ({
              label: pkg,
              value: pkg,
            }))}
            styles={reactSelectStyles}
            isSearchable
          />
        </label>
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
