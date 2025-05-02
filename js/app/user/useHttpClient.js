import { useMemo } from 'react'

/**
 * Preferably use RTK query instead of httpClient
 * @returns {httpClient}
 */
export function useHttpClient() {
  const httpClient = useMemo(() => {
    return new window._auth.httpClient()
  }, [])

  return {
    httpClient,
  }
}
