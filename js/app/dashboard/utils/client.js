import axios from 'axios'
import { createAction } from 'redux-actions'

export const tokenRefreshSuccess = createAction('TOKEN_REFRESH_SUCCESS')

export function createClient(dispatch) {

  const baseURL = location.protocol + '//' + location.host
  const client = axios.create({
    baseURL: baseURL
  })

  let subscribers = []
  let isRefreshingToken = false

  function onTokenFetched(token) {
    subscribers.forEach(callback => callback(token))
    subscribers = []
  }

  function addSubscriber(callback) {
    subscribers.push(callback)
  }

  function refreshToken() {
    return new Promise((resolve) => {
      // TODO Check response is OK, reject promise
      $.getJSON(window.Routing.generate('profile_jwt')).then(result => resolve(result.jwt))
    })
  }

  // @see https://gist.github.com/Godofbrowser/bf118322301af3fc334437c683887c5f
  // @see https://www.techynovice.com/setting-up-JWT-token-refresh-mechanism-with-axios/
  client.interceptors.response.use(
    response => response,
    error => {

      if (error.response && error.response.status === 401) {

        try {

          const req = error.config

          const retry = new Promise(resolve => {
            addSubscriber(token => {
              req.headers['Authorization'] = `Bearer ${token}`
              resolve(axios(req))
            })
          })

          if (!isRefreshingToken) {

            isRefreshingToken = true

            refreshToken()
              .then(token => {
                dispatch(tokenRefreshSuccess(token))
                return token
              })
              .then(token => onTokenFetched(token))
              .catch(error => Promise.reject(error))
              .finally(() => {
                isRefreshingToken = false
              })
          }

          return retry
        } catch (e) {
          return Promise.reject(e)
        }
      }

      return Promise.reject(error)
    }
  )

  client.paginatedRequest = async (requestConfig) => {
      const firstResp = await client.request(requestConfig)
      const data = [firstResp.data['hydra:member']]

      if (!Object.hasOwn(firstResp.data, 'hydra:view') || !Object.hasOwn(firstResp.data['hydra:view'], 'hydra:last')) {
        return data[0]
      }

      const maxPageUrl = new URL(firstResp.data['hydra:view']['hydra:last'], baseURL)
      const maxPage = parseInt(maxPageUrl.searchParams.get('page'))

      // build queries URLs
      const urls = Array.from(Array(maxPage + 1).keys()).slice(2).map((val) => {
        const url = new URL(firstResp.data['hydra:view']['hydra:last'], baseURL)
        url.searchParams.set('page', val)
        return url.toString()
      })

      await Promise.all(
          urls.map(url => client.request({...requestConfig, url}))
        ).then((values) => {
          values.forEach((res, index) => {data[index + 1] = res.data['hydra:member']})
      })

      return data.reduce((acc, current) => acc.concat(current), [])
  }

  return client
}
