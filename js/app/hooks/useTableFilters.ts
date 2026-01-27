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
  const [filters, setFilters] = useState<Record<string, string[]>>({});
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
              if (values && values.length > 0) {
                newFilters[paramKey] = values;
              } else {
                delete newFilters[paramKey];
              }
            });
          }

          if (config.iriMappings) {
            config.iriMappings.forEach(({ columnKey, mappings }) => {
              const values = tableFilters[columnKey] as string[] | undefined;
              if (values && values.length > 0) {
                mappings.forEach(({ iriPrefix, paramKey }) => {
                  const matchingValues = parseIriFilter(values, iriPrefix);
                  if (matchingValues.length > 0) {
                    newFilters[paramKey] = matchingValues;
                  } else {
                    delete newFilters[paramKey];
                  }
                });
              } else {
                mappings.forEach(({ paramKey }) => {
                  delete newFilters[paramKey];
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
    searchParams,
    onChange,
    page,
    setPage,
  };
}
