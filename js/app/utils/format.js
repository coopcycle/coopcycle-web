export function money(amount) {
  const { country, currencyCode } = document.body.dataset;
  return new Intl.NumberFormat(country, {
    style: "currency",
    currency: currencyCode,
  }).format(amount / 100);
}

export function weight(amount) {
  const { country } = document.body.dataset;
  return new Intl.NumberFormat(country, {
    style: "unit",
    unit: "kilogram",
  }).format(amount / 1000);
}
