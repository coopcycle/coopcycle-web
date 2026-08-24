import { useState, useMemo, useCallback } from 'react';
import {
  parseIriFilter,
  removeOrderFilters,
  getOrderValue,
  buildSearchParams,
} from '../utils/filter';

interface SimpleFilter {
  columnKey: string;
  paramKey: string;
}

interface IriMapping {
  iriPrefix: string;
  paramKey: string;
}

interface IriFilter {
  columnKey: string;
  mappings: IriMapping[];
}

interface UseTableFiltersConfig {
  single?: string[];
  multiple?: SimpleFilter[];
  iriMappings?: IriFilter[];
  initialFilters?: Record<string, string[]>;
}

interface TableFilters {
  [key: string]: string | number | (string | number)[] | null | undefined;
}

interface Sorter {
  order?: string;
  field?: string;
}

interface OnChangeProps {
  action?: string;
}

export function useTableFilters(config: UseTableFiltersConfig) {
  const [filters, setFilters] = useState<Record<string, string[]>>(
    config.initialFilters || {},
  );
  const [page, setPage] = useState(1);

  const onChange = useCallback(
    (
      _pagination,
      tableFilters: TableFilters,
      sorter: Sorter | Sorter[],
      { action }: OnChangeProps,
    ) => {
      if (action === 'sort') {
        const sorterObj = Array.isArray(sorter) ? sorter[0] : sorter;
        setFilters(prev => {
          const newFilters = removeOrderFilters(prev);
          const orderValue = getOrderValue(sorterObj);
          if (orderValue && sorterObj.field) {
            newFilters[`order[${sorterObj.field}]`] = [orderValue];
          }
          return newFilters;
        });
        setPage(1);
      } else if (action === 'filter') {
        setFilters(prev => {
          const newFilters = { ...prev };

          if (config.single) {
            config.single.forEach(paramKey => {
              const values = tableFilters[paramKey] as string[] | undefined;
              if (values && values.length > 0) {
                newFilters[paramKey] = [values[0]];
              } else {
                delete newFilters[paramKey];
              }
            });
          }

          if (config.multiple) {
            config.multiple.forEach(({ columnKey, paramKey }) => {
              const values = tableFilters[columnKey] as string[] | undefined;
              // PHP/Symfony only builds an array from repeated query params
              // when the key ends in `[]` — without it, `key=a&key=b` collapses
              // to the last value, silently dropping the rest.
              const key = `${paramKey}[]`;
              if (values && values.length > 0) {
                newFilters[key] = values;
              } else {
                delete newFilters[key];
              }
            });
          }

          if (config.iriMappings) {
            config.iriMappings.forEach(({ columnKey, mappings }) => {
              const values = tableFilters[columnKey] as string[] | undefined;
              if (values && values.length > 0) {
                mappings.forEach(({ iriPrefix, paramKey }) => {
                  const matchingValues = parseIriFilter(values, iriPrefix);
                  const key = `${paramKey}[]`;
                  if (matchingValues.length > 0) {
                    newFilters[key] = matchingValues;
                  } else {
                    delete newFilters[key];
                  }
                });
              } else {
                mappings.forEach(({ paramKey }) => {
                  delete newFilters[`${paramKey}[]`];
                });
              }
            });
          }

          return newFilters;
        });
        setPage(1);
      }
    },
    [config],
  );

  const searchParams = useMemo(() => buildSearchParams(filters), [filters]);

  return {
    filters,
    setFilters,
    searchParams,
    onChange,
    page,
    setPage,
  };
}
