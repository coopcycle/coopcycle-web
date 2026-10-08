import { Centrifuge } from 'centrifuge'

import { createCentrifuge, subscribe } from '../client'

jest.mock('centrifuge', () => ({ Centrifuge: jest.fn() }))

const CLIENT_ID = 'client-id-from-server'

function fakeSubscription() {
  return {
    listeners: {},
    on(event, fn) {
      this.listeners[event] = [...(this.listeners[event] ?? []), fn]
    },
    removeListener(event, fn) {
      this.listeners[event] = (this.listeners[event] ?? []).filter(l => l !== fn)
    },
    subscribe: jest.fn(),
    publish(data) {
      ;(this.listeners.publication ?? []).forEach(fn => fn({ data }))
    },
  }
}

function fakeCentrifuge() {
  const subscriptions = {}
  const handlers = {}

  return {
    subscriptions,
    on: jest.fn((event, fn) => {
      handlers[event] = [...(handlers[event] ?? []), fn]
    }),
    /** Fires what the server sends on a successful connection. */
    connect: jest.fn(function () {
      ;(handlers.connected ?? []).forEach(fn => fn({ client: CLIENT_ID }))
    }),
    ready: jest.fn(() => Promise.resolve()),
    getSubscription: jest.fn(channel => subscriptions[channel] ?? null),
    newSubscription: jest.fn(channel => {
      if (subscriptions[channel]) {
        // What centrifuge-js actually does from v3 on, and what took the
        // notifications widget down under StrictMode.
        throw new Error(`Subscription to the channel ${channel} already exists`)
      }

      subscriptions[channel] = fakeSubscription()

      return subscriptions[channel]
    }),
  }
}

/** A connected client, built the way the app builds one. */
function connectedClient() {
  Centrifuge.mockImplementation(() => fakeCentrifuge())

  const centrifuge = createCentrifuge('a-connection-token')
  centrifuge.connect()

  return centrifuge
}

describe('subscribe', () => {

  beforeEach(() => {
    jest.clearAllMocks()
  })

  it('delivers the publication payload', () => {
    const centrifuge = connectedClient()
    const onMessage = jest.fn()

    subscribe(centrifuge, 'coopcycle_events#admin', onMessage)
    centrifuge.subscriptions['coopcycle_events#admin'].publish({ event: { name: 'task:done' } })

    expect(onMessage).toHaveBeenCalledWith({ event: { name: 'task:done' } })
  })

  it('reuses an existing subscription instead of throwing', () => {
    const centrifuge = connectedClient()

    subscribe(centrifuge, 'coopcycle_events#admin', jest.fn())

    // React runs effects twice under StrictMode, so this is the second pass.
    expect(() => subscribe(centrifuge, 'coopcycle_events#admin', jest.fn()))
      .not.toThrow()

    expect(centrifuge.newSubscription).toHaveBeenCalledTimes(1)
  })

  it('delivers to every listener on a shared subscription', () => {
    const centrifuge = connectedClient()
    const first = jest.fn()
    const second = jest.fn()

    subscribe(centrifuge, 'coopcycle_events#admin', first)
    subscribe(centrifuge, 'coopcycle_events#admin', second)

    centrifuge.subscriptions['coopcycle_events#admin'].publish({ ok: true })

    expect(first).toHaveBeenCalledWith({ ok: true })
    expect(second).toHaveBeenCalledWith({ ok: true })
  })

  it('cleanup detaches only its own listener', () => {
    const centrifuge = connectedClient()
    const staying = jest.fn()
    const leaving = jest.fn()

    subscribe(centrifuge, 'coopcycle_events#admin', staying)
    const unsubscribe = subscribe(centrifuge, 'coopcycle_events#admin', leaving)

    unsubscribe()
    centrifuge.subscriptions['coopcycle_events#admin'].publish({ ok: true })

    expect(leaving).not.toHaveBeenCalled()
    expect(staying).toHaveBeenCalledWith({ ok: true })
  })

  it('only asks for a subscription token when the channel needs one', () => {
    const centrifuge = connectedClient()

    subscribe(centrifuge, 'coopcycle_events#admin', jest.fn())
    expect(centrifuge.newSubscription).toHaveBeenLastCalledWith('coopcycle_events#admin', {})

    subscribe(centrifuge, '$coopcycle_tracking', jest.fn(), { needsToken: true })
    expect(centrifuge.newSubscription).toHaveBeenLastCalledWith(
      '$coopcycle_tracking',
      expect.objectContaining({ getToken: expect.any(Function) })
    )
  })

  /**
   * The subscription `getToken` context carries only the channel. The client id
   * has to come from the connection, and leaving it out is what made the
   * endpoint answer 400.
   */
  it('sends the client id with the token request', async () => {
    const centrifuge = connectedClient()

    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ token: 'a-subscription-token' }),
    })

    subscribe(centrifuge, '$coopcycle_tracking', jest.fn(), { needsToken: true })

    const { getToken } = centrifuge.newSubscription.mock.calls.at(-1)[1]

    await expect(getToken({ channel: '$coopcycle_tracking' }))
      .resolves.toEqual('a-subscription-token')

    const [ url, request ] = global.fetch.mock.calls.at(-1)

    expect(url).toEqual('/centrifuge/subscription-token')
    expect(JSON.parse(request.body)).toEqual({
      channel: '$coopcycle_tracking',
      client: CLIENT_ID,
    })
  })

  it('refuses a token request rather than sending one the server must reject', async () => {
    Centrifuge.mockImplementation(() => fakeCentrifuge())

    // Never connected, so no client id was ever assigned.
    const centrifuge = createCentrifuge('a-connection-token')

    global.fetch = jest.fn()

    subscribe(centrifuge, '$coopcycle_tracking', jest.fn(), { needsToken: true })

    const { getToken } = centrifuge.newSubscription.mock.calls.at(-1)[1]

    await expect(getToken({ channel: '$coopcycle_tracking' })).rejects.toThrow('No client id')
    expect(global.fetch).not.toHaveBeenCalled()
  })
})
