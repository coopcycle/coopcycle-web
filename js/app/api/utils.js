/**
 * similar function exists in the mobile app codebase
 */
export async function fetchAllRecordsUsingFetchWithBQ(
  fetchWithBQ,
  url,
  itemsPerPage,
  otherParams = null,
) {
  const fetch = async page => {
    const params = new URLSearchParams({
      pagination: true,
      page,
      itemsPerPage,
      ...otherParams,
    })
    const result = await fetchWithBQ(`${url}?${params.toString()}`)
    return result.data
  }
  const firstRs = await fetch(1)

  if (
    !Object.hasOwn(firstRs, 'hydra:totalItems') ||
    firstRs['hydra:totalItems'] <= firstRs['hydra:member'].length
  ) {
    // Total items were already returned in the 1st request!
    return {
      data: firstRs['hydra:member'],
    }
  }

  // OK more pages are needed to be fetched to get all items..!
  const totalItems = firstRs['hydra:totalItems']
  const maxPage =
    Math.trunc(totalItems / itemsPerPage) +
    (totalItems % itemsPerPage === 0 ? 0 : 1)

  return Promise.all(
    [...Array(maxPage + 1).keys()].slice(2).map(page => fetch(page)),
  )
    .then(results =>
      results.reduce(
        (acc, rs) => acc.concat(rs['hydra:member']),
        firstRs['hydra:member'],
      ),
    )
    .then(results => {
      return { data: results }
    })
    .catch(error => {
      return { error }
    })
}
