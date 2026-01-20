export interface NamedEntity {
  id: number;
  name?: string;
  username?: string;
}

export interface FilterOption {
  text: string;
  value: string | number;
}

export function toFilterOptions<T extends NamedEntity>(
  items: T[],
  getLabel: (item: T) => string,
  toIri?: (id: number) => string,
): FilterOption[] {
  if (!items || items.length === 0) {
    return [];
  }

  return items.map(item => ({
    text: getLabel(item),
    value: toIri ? toIri(item.id) : item.id,
  }));
}

export function buildSearchParams(filters: Record<string, string[]>): string {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, values]) => {
    if (values && values.length > 0) {
      values.forEach(value => {
        params.append(key, value);
      });
    }
  });

  return params.toString();
}

export function parseIriFilter(values: string[], prefix: string): string[] {
  if (!values || values.length === 0) {
    return [];
  }

  return values.filter(value => value.startsWith(prefix));
}

export function removeOrderFilters(
  filters: Record<string, string[]>,
): Record<string, string[]> {
  const result = { ...filters };
  Object.keys(result).forEach(key => {
    if (key.startsWith('order[')) {
      delete result[key];
    }
  });
  return result;
}

export function getOrderValue(sorter: {
  order?: string;
}): 'ASC' | 'DESC' | null {
  if (!sorter.order) {
    return null;
  }
  return sorter.order === 'ascend' ? 'ASC' : 'DESC';
}
