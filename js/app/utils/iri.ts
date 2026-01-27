export function storeToIri(id: number): string {
  return `/api/stores/${id}`;
}

export function restaurantToIri(id: number): string {
  return `/api/restaurants/${id}`;
}

export function userToIri(id: number): string {
  return `/api/users/${id}`;
}

export function iriToId(iri: string): number {
  const parts = iri.split('/');
  const lastPart = parts[parts.length - 1];
  return parseInt(lastPart, 10);
}

export function getIriPrefix(iri: string): string {
  const parts = iri.split('/');
  if (parts.length < 2) {
    return '';
  }
  return `/${parts[1]}/${parts[2]}`;
}
