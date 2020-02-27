// index.js

import _ from 'lodash'

const hostname = `//${window.location.hostname}`
               + (window.location.port ? `:${window.location.port}` : '')

const getToken = () => $.getJSON(window.Routing.generate('profile_jwt'))

let socket
let token

let isCreatingSocket = false
let socketListeners = []

function addSocketListener(cb) {
  socketListeners.push(cb)
}

function notifySocketListeners(socket) {
  socketListeners.forEach(cb => cb(socket))
  socketListeners = []
}

let isRefreshingToken = false

function refreshToken() {

  if (isRefreshingToken) {
    return
  }

  isRefreshingToken = true

  getToken().then(token => {

    // console.log('TOKEN REFRESHED')

    let events = _.mapKeys(socket._callbacks, (value, key) => key.substring(1))
    // events = _.filter(events, (value, key) => {
    //   return key !== 'error'
    // })
    // // const

    console.log('PREV EVENTS', events)

    socket.off()
    socket.close()

    socket = io(hostname, {
      path: '/tracking/socket.io',
      transports: [ 'websocket' ],
      query: { token },
    })

    // socket.on('connect', () => {
    //   console.log('SOCKET.IO CONNECT!')
    // })

    // socket.on('error', (message) => {
    //   if (message === 'Authentication error') {
    //     console.log('HTTP 401')
    //     refreshToken()
    //   }
    // })

    _.forEach(events, (callbacks, event) => {
      _.forEach(callbacks, (cb) => socket.on(event, cb))
    })

    isRefreshingToken = false
  })
}

export const createSocketIo = () => {

  return new Promise((resolve, reject) => {

    if (socket) {
      console.log('Returning existing socket')
      return resolve(socket)
    }

    if (isCreatingSocket) {
      console.log('Already creating socket')
      addSocketListener(resolve)
      return
    }

    isCreatingSocket = true

    getToken().then(token => {

      socket = io(hostname, {
        path: '/tracking/socket.io',
        transports: [ 'websocket' ],
        query: { token },
      })

      // socket.on('connect', () => {
      //   console.log('SOCKET.IO CONNECT!')
      // })

      socket.on('error', (message) => {
        if (message === 'Authentication error') {
          console.log('HTTP 401')
          refreshToken()
        }
      })

      console.log('Socket created')

      resolve(socket)
      notifySocketListeners(socket)

      isCreatingSocket = false
    })

    // if (!socket) {
    //   if (!token) {

    //   }
    // } else {
    //   resolve(socket)
    //   // addSocketListener(() => resolve(socket))
    // }


    // socket.listeners("connect");
  })

}
