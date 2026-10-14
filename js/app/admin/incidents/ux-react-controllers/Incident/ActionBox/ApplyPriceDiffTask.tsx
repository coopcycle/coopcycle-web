import React, { useState } from 'react';
import { Button, InputNumber } from 'antd';
import { useTranslation } from 'react-i18next';
import { useSelector } from 'react-redux';
import { Order } from '../../../../../api/types';
import { money } from '../../../utils';
import { centsInputNumberProps } from '../../../../../utils/format';
import { calculate } from '../../../../../utils/tax';
import {
  selectServiceTaxRate,
  ServiceTaxRate,
} from '../../../[id]/redux/incidentSlice';

type Diff = {
  taxExcluded: number | null;
  taxIncluded: number | null;
};

const emptyDiff: Diff = { taxExcluded: null, taxIncluded: null };

async function _handleApplyPriceDiff(id, diff) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate('_api_/incidents/{id}/action_put', { id }),
    { action: 'applied_price_diff', diff },
  );
}

// Amounts are in cents. Taxes are computed like on the server,
// see Sylius\Component\Taxation\Calculator\DefaultCalculator
function _fromTaxExcluded(
  taxExcluded: number | null,
  { amount, includedInPrice }: ServiceTaxRate,
): Diff {
  if (taxExcluded === null) {
    return emptyDiff;
  }

  const taxIncluded = includedInPrice
    ? Math.round(taxExcluded * (1 + amount))
    : taxExcluded + calculate(taxExcluded, amount, false);

  return { taxExcluded, taxIncluded };
}

function _fromTaxIncluded(
  taxIncluded: number | null,
  { amount, includedInPrice }: ServiceTaxRate,
): Diff {
  if (taxIncluded === null) {
    return emptyDiff;
  }

  const taxExcluded = includedInPrice
    ? taxIncluded - calculate(taxIncluded, amount, true)
    : Math.round(taxIncluded / (1 + amount));

  return { taxExcluded, taxIncluded };
}

// The server stores the amount the same way the service tax rate does
function _diffToSubmit(diff: Diff, serviceTaxRate: ServiceTaxRate | null) {
  if (!serviceTaxRate || serviceTaxRate.includedInPrice) {
    return diff.taxIncluded;
  }

  return diff.taxExcluded;
}

const styles = {
  input: {
    width: '100%',
  },
};

function Preview({
  order,
  withTax,
  taxExcluded,
  taxIncluded,
}: {
  order: Order;
  withTax: boolean;
  taxExcluded: number;
  taxIncluded: number;
}) {
  const { t } = useTranslation();

  return (
    <table className="table table-condensed my-4">
      {withTax && (
        <thead>
          <tr>
            <th></th>
            <th className="text-right">{t('INCIDENTS_TAX_EXCLUDED')}</th>
            <th className="text-right">{t('INCIDENTS_TAX_INCLUDED')}</th>
          </tr>
        </thead>
      )}
      <tbody>
        <tr>
          <td>{t('CURRENT_PRICE')}</td>
          {withTax && (
            <td className="text-right text-monospace">
              {money(order.total - order.taxTotal)}
            </td>
          )}
          <td className="text-right text-monospace">{money(order.total)}</td>
        </tr>
        <tr>
          <td>{t('INCIDENTS_PRICE_DIFF')}</td>
          {withTax && (
            <td className="text-right text-monospace">{money(taxExcluded)}</td>
          )}
          <td className="text-right text-monospace">{money(taxIncluded)}</td>
        </tr>
        <tr>
          <th>{t('NEW_PRICE')}</th>
          {withTax && (
            <th
              className="text-right text-monospace"
              data-testid="new-price-tax-excluded">
              {money(order.total - order.taxTotal + taxExcluded)}
            </th>
          )}
          <th
            className="text-right text-monospace"
            data-testid="new-price-tax-included">
            {money(order.total + taxIncluded)}
          </th>
        </tr>
      </tbody>
    </table>
  );
}

export default function ({ incident, order }) {
  const { currencySymbol } = document.body.dataset;
  const { t } = useTranslation();

  const serviceTaxRate = useSelector(selectServiceTaxRate);
  // Without a rate, or when it is zero, both amounts are the same
  const withTax = !!serviceTaxRate && serviceTaxRate.amount > 0;

  const [diff, setDiff] = useState<Diff>(emptyDiff);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  const value = _diffToSubmit(diff, serviceTaxRate);

  const inputProps = {
    addonAfter: currencySymbol,
    status: error ? ('error' as const) : undefined,
    size: 'large' as const,
    style: styles.input,
    ...centsInputNumberProps,
  };

  return (
    <div>
      {withTax ? (
        <div className="row">
          <div className="col-xs-6">
            <label htmlFor="price-diff-tax-excluded">
              {t('INCIDENTS_PRICE_DIFF_TAX_EXCLUDED')}
            </label>
            <InputNumber
              id="price-diff-tax-excluded"
              data-testid="price-diff-tax-excluded"
              value={diff.taxExcluded}
              onChange={taxExcluded =>
                setDiff(_fromTaxExcluded(taxExcluded, serviceTaxRate))
              }
              {...inputProps}
            />
          </div>
          <div className="col-xs-6">
            <label htmlFor="price-diff-tax-included">
              {t('INCIDENTS_PRICE_DIFF_TAX_INCLUDED')}
            </label>
            <InputNumber
              id="price-diff-tax-included"
              data-testid="price-diff-tax-included"
              value={diff.taxIncluded}
              onChange={taxIncluded =>
                setDiff(_fromTaxIncluded(taxIncluded, serviceTaxRate))
              }
              {...inputProps}
            />
          </div>
        </div>
      ) : (
        <InputNumber
          data-testid="price-diff-tax-included"
          value={diff.taxIncluded}
          onChange={amount =>
            setDiff({ taxExcluded: amount, taxIncluded: amount })
          }
          {...inputProps}
        />
      )}
      {!!value && (
        <Preview
          order={order}
          withTax={withTax}
          taxExcluded={diff.taxExcluded ?? 0}
          taxIncluded={diff.taxIncluded ?? 0}
        />
      )}
      <p className="mt-3">
        <Button
          data-testid="submit-price-diff-button"
          disabled={!value || submitting}
          onClick={async () => {
            setSubmitting(true);
            const { error } = await _handleApplyPriceDiff(incident.id, value);
            if (!error) {
              location.reload();
            } else {
              setError(true);
              setSubmitting(false);
            }
          }}>
          {t('APPLY')}
        </Button>
      </p>
    </div>
  );
}
