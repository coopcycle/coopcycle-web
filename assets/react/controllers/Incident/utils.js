export function money(amount) {
  const { coutry, currencyCode } = document.body.dataset;
  return new Intl.NumberFormat(coutry, {
    style: "currency",
    currency: currencyCode,
  }).format(amount / 100);
}

export async function incidentAction(id, payload) {
  return fetch(window.Routing.generate("api_incidents_action_item", { id }), {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${window._auth.jwt}`,
    },
    body: JSON.stringify(payload),
  });
}
