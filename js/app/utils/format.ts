import { parsePhoneNumberFromString } from 'libphonenumber-js';
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
