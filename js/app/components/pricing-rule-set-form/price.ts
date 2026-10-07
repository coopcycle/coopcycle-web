import React, { useContext } from 'react';

/*
  Pricing rules store their prices with tax included, and keep doing so: this is only
  about what the form asks for. Most of the time the cooperative invoices a store
  without tax, so the amount being typed is the one without it.
*/
export const TaxRateContext = React.createContext(0);

export const useTaxRate = (): number => useContext(TaxRateContext);

/** Cents without tax, from the cents with tax that are stored. */
export const toTaxExcluded = (taxIncluded: number, rate: number): number =>
  Math.round(taxIncluded / (1 + rate));

/** Cents with tax to store, from the cents without tax that were typed. */
export const toTaxIncluded = (taxExcluded: number, rate: number): number =>
  Math.round(taxExcluded * (1 + rate));
