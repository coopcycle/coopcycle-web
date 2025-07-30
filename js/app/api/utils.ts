import { HydraCollection } from './types'
import { BaseQueryFn } from '@reduxjs/toolkit/query'

// RTK Query result types - returns array of items, not HydraCollection
interface QueryFnSuccessResult<T> {
  data: T[]
  error?: never
}

interface QueryFnErrorResult {
  data?: never
  error
}

// Union type for RTK Query queryFn result
type QueryFnResult<T> = QueryFnSuccessResult<T> | QueryFnErrorResult

/**
 * similar function exists in the mobile app codebase
 */
export async function fetchAllRecordsUsingFetchWithBQ<T>(
  fetchWithBQ: (arg: Parameters<BaseQueryFn>[0]) => ReturnType<BaseQueryFn>,
  url: string,
  itemsPerPage: number,
  otherParams: Record<string, string> | null = null,
): Promise<QueryFnResult<T>> {
  const fetch = async (
    page: number,
  ): Promise<{ data: HydraCollection<T> | null; error }> => {
    const params = new URLSearchParams({
      pagination: 'true',
      page: page.toString(),
      itemsPerPage: itemsPerPage.toString(),
      ...otherParams,
    })

    try {
      const result = await fetchWithBQ(`${url}?${params.toString()}`)
      return { data: result.data, error: null }
    } catch (err) {
      return { data: null, error: err }
    }
  }

  const firstPageResult = await fetch(1)
  if (firstPageResult.error) {
    return { error: firstPageResult.error }
  }

  const firstPageData = firstPageResult.data
  if (
    !firstPageData ||
    !Object.hasOwn(firstPageData, 'hydra:totalItems') ||
    firstPageData['hydra:totalItems'] <= firstPageData['hydra:member'].length
  ) {
    // Total items were already returned in the 1st request!
    return {
      data: firstPageData?.['hydra:member'] || [],
    }
  }

  // OK more pages are needed to be fetched to get all items..!
  const totalItems = firstPageData['hydra:totalItems']
  const maxPage =
    Math.trunc(totalItems / itemsPerPage) +
    (totalItems % itemsPerPage === 0 ? 0 : 1)

  return Promise.all(
    [...Array(maxPage + 1).keys()].slice(2).map(page => fetch(page)),
  )
    .then(results => {
      // Check if any page has an error
      const errorResult = results.find(result => result.error)
      if (errorResult) {
        return { error: errorResult.error }
      }

      // Combine all data from successful results
      const combinedData = results.reduce(
        (acc, result) => acc.concat(result.data?.['hydra:member'] || []),
        firstPageData['hydra:member'],
      )

      return { data: combinedData }
    })
    .catch(error => {
      return { error }
    })
}
