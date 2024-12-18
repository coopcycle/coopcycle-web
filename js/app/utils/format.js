export function money(amount) {
  const { coutry, currencyCode } = document.body.dataset;
  return new Intl.NumberFormat(coutry, {
    style: "currency",
    currency: currencyCode,
  }).format(amount / 100);
}

export function weight(amount) {
  const { coutry } = document.body.dataset;
  return new Intl.NumberFormat(coutry, {
    style: "unit",
    unit: "kilogram",
  }).format(amount / 1000);
}
