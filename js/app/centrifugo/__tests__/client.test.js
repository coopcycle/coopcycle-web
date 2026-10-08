import { subscribe } from '../client'

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

  return {
    subscriptions,
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

describe('subscribe', () => {

  it('delivers the publication payload', () => {
    const centrifuge = fakeCentrifuge()
    const onMessage = jest.fn()

    subscribe(centrifuge, 'coopcycle_events#admin', onMessage)
    centrifuge.subscriptions['coopcycle_events#admin'].publish({ event: { name: 'task:done' } })

    expect(onMessage).toHaveBeenCalledWith({ event: { name: 'task:done' } })
  })

  it('reuses an existing subscription instead of throwing', () => {
    const centrifuge = fakeCentrifuge()

    subscribe(centrifuge, 'coopcycle_events#admin', jest.fn())

    // React runs effects twice under StrictMode, so this is the second pass.
    expect(() => subscribe(centrifuge, 'coopcycle_events#admin', jest.fn()))
      .not.toThrow()

    expect(centrifuge.newSubscription).toHaveBeenCalledTimes(1)
  })

  it('delivers to every listener on a shared subscription', () => {
    const centrifuge = fakeCentrifuge()
    const first = jest.fn()
    const second = jest.fn()

    subscribe(centrifuge, 'coopcycle_events#admin', first)
    subscribe(centrifuge, 'coopcycle_events#admin', second)

    centrifuge.subscriptions['coopcycle_events#admin'].publish({ ok: true })

    expect(first).toHaveBeenCalledWith({ ok: true })
    expect(second).toHaveBeenCalledWith({ ok: true })
  })

  it('cleanup detaches only its own listener', () => {
    const centrifuge = fakeCentrifuge()
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
    const centrifuge = fakeCentrifuge()

    subscribe(centrifuge, 'coopcycle_events#admin', jest.fn())
    expect(centrifuge.newSubscription).toHaveBeenLastCalledWith('coopcycle_events#admin', {})

    subscribe(centrifuge, '$coopcycle_tracking', jest.fn(), { needsToken: true })
    expect(centrifuge.newSubscription).toHaveBeenLastCalledWith(
      '$coopcycle_tracking',
      expect.objectContaining({ getToken: expect.any(Function) })
    )
  })
})
