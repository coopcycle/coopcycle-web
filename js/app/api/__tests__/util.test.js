import { fetchAllRecordsUsingFetchWithBQ } from '../utils'

describe('fetchAllRecordsUsingFetchWithBQ', () => {
  const members = [{ '@id': '/api/stores/1' }, { '@id': '/api/stores/2' }]

  it('should return all items that fits in the first page', async () => {
    const fetchWithBQ = jest.fn()
    fetchWithBQ.mockResolvedValue({
      data: {
        'hydra:totalItems': 2,
        'hydra:member': members,
      },
    })

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      10,
    )
    expect(rs).toEqual({ data: members })
    expect(fetchWithBQ).toHaveBeenCalledTimes(1)
  })

  it('should return all items from 3 request', async () => {
    const fetchWithBQ = jest.fn()
    fetchWithBQ.mockResolvedValue({
      data: {
        'hydra:totalItems': 6,
        'hydra:member': members,
      },
    })

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      2,
    )
    expect(rs).toEqual({ data: [...members, ...members, ...members] })
    expect(fetchWithBQ).toHaveBeenCalledTimes(3)
  })

  it('should return all items from 1 request although totalItems is bigger than member.length but itemsPerPage is bigger', async () => {
    const fetchWithBQ = jest.fn()
    fetchWithBQ.mockResolvedValue({
      data: {
        'hydra:totalItems': 5,
        'hydra:member': members,
      },
    })

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      7,
    )
    expect(rs).toEqual({ data: members })
    expect(fetchWithBQ).toHaveBeenCalledTimes(1)
  })

  it('should return all items from 1 request although totalItems is equal to member.length and itemsPerPage is lower', async () => {
    const fetchWithBQ = jest.fn()
    fetchWithBQ.mockResolvedValue({
      data: {
        'hydra:totalItems': 2,
        'hydra:member': members,
      },
    })

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      1,
    )
    expect(rs).toEqual({ data: members })
    expect(fetchWithBQ).toHaveBeenCalledTimes(1)
  })

  it('should return error when first page request fails', async () => {
    const fetchWithBQ = jest.fn()
    const error = new Error('Network error')
    fetchWithBQ.mockRejectedValue(error)

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      10,
    )
    expect(rs).toEqual({
      error: {
        status: 'CUSTOM_ERROR',
        data: error,
        error: 'fetch failed',
      },
    })
    expect(fetchWithBQ).toHaveBeenCalledTimes(1)
  })

  it('should return error when subsequent page request fails', async () => {
    const fetchWithBQ = jest.fn()
    const error = new Error('Network error on page 2')

    // First call succeeds
    fetchWithBQ.mockResolvedValueOnce({
      data: {
        'hydra:totalItems': 4,
        'hydra:member': members,
      },
    })

    // Second call fails
    fetchWithBQ.mockRejectedValueOnce(error)

    const rs = await fetchAllRecordsUsingFetchWithBQ(
      fetchWithBQ,
      '/api/stores',
      2,
    )
    expect(rs).toEqual({
      error: {
        status: 'CUSTOM_ERROR',
        data: error,
        error: 'fetch failed',
      },
    })
    expect(fetchWithBQ).toHaveBeenCalledTimes(2)
  })
})
