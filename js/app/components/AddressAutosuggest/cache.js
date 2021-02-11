import engine  from 'store/src/store-engine'
import session from 'store/storages/sessionStorage'
import cookie  from 'store/storages/cookieStorage'
import expirePlugin from 'store/plugins/expire'

export const storage = engine.createStore([ session, cookie ], [ expirePlugin ], 'AddressAutosuggest')

export const getFromCache = (value) => {

  const cacheKeys = []
  storage.each(function(cachedValue, cacheKey) {
    if (value.length > cacheKey.length && value.startsWith(cacheKey)) {
      cacheKeys.push(cacheKey)
    }
  })

  if (cacheKeys.length > 0) {
    cacheKeys.sort((a, b) =>
      a.length === b.length ? 0 : (a.length > b.length ? -1 : 1))
    const cacheKey = cacheKeys[0]
    const cachedResults = storage.get(cacheKey)
    if (cachedResults) {
      return cachedResults
    }
  }

  return []
}
