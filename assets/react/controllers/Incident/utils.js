export function money(amount) {
  const { coutry, currencyCode } = document.body.dataset;
  return new Intl.NumberFormat(coutry, {
    style: "currency",
    currency: currencyCode,
  }).format(amount / 100);
}
