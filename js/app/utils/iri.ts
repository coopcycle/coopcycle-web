export function storeToIri(id: number): string {
  return `/api/stores/${id}`;
}

export function restaurantToIri(id: number): string {
  return `/api/restaurants/${id}`;
}

export function userToIri(id: number): string {
  return `/api/users/${id}`;
}
