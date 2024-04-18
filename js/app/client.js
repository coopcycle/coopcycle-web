import axios from 'axios'

export default function(token, refreshToken, refreshTokenCallback) {

  const client = axios.create({
    baseURL: location.protocol + '//' + location.host
  })

  let subscribers = []
  let isRefreshingToken = false

  function onTokenFetched(t) {
    subscribers.forEach(callback => callback(t))
    subscribers = []

    token = t

    if (refreshTokenCallback && typeof refreshTokenCallback === 'function') {
      refreshTokenCallback(t)
    }
  }

  function addSubscriber(callback) {
    subscribers.push(callback)
  }

  client.defaults.headers.common['Accept'] = 'application/ld+json'
  client.defaults.headers.common['Content-Type'] = 'application/ld+json'

  client.interceptors.request.use(
    config => {

      let headers = { ...config.headers }

      if (!Object.prototype.hasOwnProperty.call(headers, 'Authorization')) {
        headers = {
          ...headers,
          'Authorization': `Bearer ${token}`,
        }
      }

      // Make sure Content-Type header is not removed automatically
      // https://github.com/axios/axios/issues/1535
      if (['post', 'put'].includes(config.method.toLowerCase()) && !config.data) {
        config = {
          ...config,
          data: {},
        }
      }

      return {
        ...config,
        headers
      }
    },
    error => error
  );

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

  return client
}
