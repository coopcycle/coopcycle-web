import { Centrifuge } from 'centrifuge'

/**
 * Our Centrifugo server still runs with `use_client_protocol_v1_by_default`, a
 * compatibility shim kept for clients on centrifuge-js v2 -- the mobile app is
 * still one of them. A modern SDK speaks protocol v2 and has to say so, or the
 * server assumes v1 and the connection fails.
 *
 * @see https://centrifugal.dev/docs/getting-started/migration_v4
 */
const CONNECTION_URL_PARAMS = 'cf_protocol_version=v2'

function connectionUrl() {
  const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws'

  return `${protocol}://${window.location.host}/centrifugo/connection/websocket?${CONNECTION_URL_PARAMS}`
}

/**
 * Asks the backend for a fresh connection token.
 *
 * Connection tokens last an hour. When one is about to expire the SDK calls
 * this; returning a token keeps the connection, throwing drops it. Returning
 * nothing silently would leave the client connected with a dead token until the
 * server closes it, which is the kind of failure nobody notices until a
 * dispatch board has been stale for ten minutes.
 */
async function refreshToken() {
  const response = await fetch('/centrifuge/refresh', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`Could not refresh the Centrifugo token (${response.status})`)
  }

  const { token } = await response.json()

  return token
}

/**
 * A Centrifugo client, connected and ready to take subscriptions.
 *
 * @param {string} token Connection token rendered into the page
 * @param {Object} options
 * @param {boolean} options.refresh Whether to renew the token when it expires.
 *   Pass false on pages served to someone without a session -- the order
 *   tracking page hands an anonymous visitor a short-lived token scoped to one
 *   order, and there is nobody for `/centrifuge/refresh` to issue a new one to.
 *   The connection is then simply dropped when the token runs out.
 * @param {function} options.onConnected Called once the connection is live
 * @param {function} options.onDisconnected Called when it drops
 */
export function createCentrifuge(token, { refresh = true, onConnected, onDisconnected } = {}) {
  const centrifuge = new Centrifuge(connectionUrl(), {
    token,
    ...(refresh ? { getToken: refreshToken } : {}),
  })

  centrifuge.on('connected', ctx => clientIds.set(centrifuge, ctx.client))

  if (onConnected) {
    centrifuge.on('connected', onConnected)
  }

  if (onDisconnected) {
    centrifuge.on('disconnected', onDisconnected)
  }

  return centrifuge
}

/**
 * The id the server assigned to each connection.
 *
 * A subscription token is bound to one connection, so the backend needs the
 * client id -- but centrifuge-js does not expose it (`_client` is private) and
 * the subscription `getToken` context carries only the channel. It arrives once,
 * on the `connected` event, so it is kept here against the client it belongs to.
 */
const clientIds = new WeakMap()

/**
 * Asks the backend for a token authorising this connection on this channel.
 */
async function subscriptionToken(centrifuge, channel) {
  // The id is only known once connected. Subscribing before the connection is
  // up is normal -- the SDK queues it -- so wait rather than send a request the
  // server can only reject.
  await centrifuge.ready()

  const client = clientIds.get(centrifuge)

  if (!client) {
    throw new Error(`No client id yet, cannot request a token for ${channel}`)
  }

  const response = await fetch('/centrifuge/subscription-token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ channel, client }),
  })

  if (!response.ok) {
    throw new Error(`Not allowed to subscribe to ${channel} (${response.status})`)
  }

  const { token } = await response.json()

  return token
}

/**
 * Subscribes to a channel and hands each publication's payload to `onMessage`.
 *
 * In centrifuge-js v2 a subscription was created and subscribed in one call, and
 * the callback received the whole message. From v3 on a subscription is an
 * object with its own lifecycle, and the payload arrives as `ctx.data` -- so
 * this exists to keep that detail in one place rather than in seven.
 *
 * Subscribing twice to the same channel on one client is tolerated: `v2` returned
 * the existing subscription, whereas `newSubscription()` throws. That difference
 * is not theoretical -- React runs effects twice under StrictMode, so the
 * notifications widget asked for its channel twice on every page load and the
 * second call took the whole component down.
 *
 * @param {Object} options
 * @param {boolean} options.needsToken Whether the channel requires a
 *   subscription token. Centrifugo authorises a connection on its own
 *   user-limited channels (`..._events#<username>`) from the connection token,
 *   but refuses anything shared with "permission denied" unless a token is
 *   presented -- the tracking channel being the one that matters here.
 * @returns {function} detaches this listener -- suitable as a React effect
 *   cleanup. Only the listener is removed, not the subscription: it may be
 *   shared with another caller on the same channel.
 */
export function subscribe(centrifuge, channel, onMessage, { needsToken = false } = {}) {
  const subscription = centrifuge.getSubscription(channel)
    ?? centrifuge.newSubscription(
      channel,
      needsToken ? { getToken: ctx => subscriptionToken(centrifuge, ctx.channel) } : {},
    )

  const listener = ctx => onMessage(ctx.data)

  subscription.on('publication', listener)
  subscription.subscribe()

  return () => subscription.removeListener('publication', listener)
}
