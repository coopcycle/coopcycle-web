import { parsePhoneNumberFromString } from 'libphonenumber-js';
import type { InputNumberProps } from 'antd';
import { getCountry } from '../i18n';

export function money(amount: number): string {
  const { country, currencyCode } = document.body.dataset;
  return new Intl.NumberFormat(country, {
    style: 'currency',
    currency: currencyCode,
  }).format(amount / 100);
}

export function weight(amount: number): string {
  const { country } = document.body.dataset;
  return new Intl.NumberFormat(country, {
    style: 'unit',
    unit: 'kilogram',
  }).format(amount / 1000);
}

export function phoneNumber(value: string): string {
  const phoneNumber = parsePhoneNumberFromString(
    value,
    (getCountry() || 'fr').toUpperCase(),
  );
  return phoneNumber ? phoneNumber.formatNational() : value;
}

type InputNumberFormatterInfo = { userTyping: boolean; input: string };

/**
 * True for `null`, `undefined`, or `''`. Unlike `_.isEmpty` this does NOT
 * consider `0`, `false`, `[]`, or `{}` as empty — useful when `0` is a
 * meaningful input (e.g. an amount of zero).
 */
export function isNilOrEmpty(value: unknown): value is null | undefined | '' {
  return value == null || value === '';
}

function getInputNumberFormat(): Intl.NumberFormat {
  const { country } = document.body.dataset;
  return new Intl.NumberFormat(country, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: false,
  });
}

function parseAmount(value: unknown, scale: number): number {
  if (isNilOrEmpty(value)) {
    return 0;
  }
  const raw = String(value);
  const isNegative = raw.trimStart().startsWith('-');
  // Accept both "," and "." as decimal separators, regardless of locale.
  const normalized = raw.replace(',', '.').replace(/[^0-9.]/g, '');
  const parsed = parseFloat(normalized);
  if (Number.isNaN(parsed)) {
    return 0;
  }
  // Round when scaling up to whole units (e.g. cents) to avoid float drift.
  const scaled = scale > 1 ? Math.round(parsed * scale) : parsed;
  return isNegative ? -scaled : scaled;
}

function formatAmount(
  value: unknown,
  scale: number,
  info?: InputNumberFormatterInfo,
): string {
  if (info?.userTyping) {
    return info.input;
  }
  if (isNilOrEmpty(value)) {
    return '';
  }
  const numeric = Number(value);
  if (Number.isNaN(numeric)) {
    return '';
  }
  return getInputNumberFormat().format(numeric / scale);
}

/**
 * antd InputNumber formatter/parser pair for fields where the internal value
 * is stored in cents (e.g. payment, refund, credit-note amounts).
 *
 * Accepts both `,` and `.` as decimal separators so users can type
 * `2,5` or `2.5` regardless of their locale.
 */
export const centsInputNumberProps = {
  formatter: (value: unknown, info?: InputNumberFormatterInfo): string =>
    formatAmount(value, 100, info),
  parser: (value: unknown): number => parseAmount(value, 100),
} satisfies Pick<InputNumberProps, 'formatter' | 'parser'>;

/**
 * antd InputNumber formatter/parser pair for fields where the internal value
 * is stored in major units (e.g. euros).
 *
 * Accepts both `,` and `.` as decimal separators so users can type
 * `2,5` or `2.5` regardless of their locale.
 */
export const amountInputNumberProps = {
  formatter: (value: unknown, info?: InputNumberFormatterInfo): string =>
    formatAmount(value, 1, info),
  parser: (value: unknown): number => parseAmount(value, 1),
} satisfies Pick<InputNumberProps, 'formatter' | 'parser'>;