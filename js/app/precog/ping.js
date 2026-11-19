import axios from 'axios'

/**
 * How often a checkout page tells the server it is still there.
 *
 * The server keeps a ghost for 90 s after the last heartbeat, so two beats can
 * go missing -- a laptop lid, a tunnel, a slow request -- without the halo
 * blinking off the dispatch board. Closing the tab stops the beat, and the
 * ghost fades within a minute and a half, which is also what abandoning the
 * cart looks like. That is deliberate: the two are the same thing.
 */
const INTERVAL = 30 * 1000

/**
 * Tells the server which checkout page this is.
 *
 * The page only declares where it is; the server re-reads the cart and decides
 * which rung of the funnel that actually puts it on. A page cannot promote
 * itself by claiming to be further along than its cart is.
 *
 * @param {'cart'|'checkout'|'payment'} page
 * @returns {{ submitted: function, stop: function }} `submitted()` marks the
 *   payment as being confirmed right now -- the top rung, and the only signal
 *   the board is meant to interrupt anyone for.
 */
export function startPrecogPing(page) {

  let submitted = false
  let timer = null
  let inFlight = false

  const beat = () => {
    // Nothing is retried and nothing is queued. A heartbeat that fails is
    // indistinguishable from one that was never sent, and the next one is
    // thirty seconds away; piling up requests to describe a cart that has
    // since moved on would be worse than missing a beat.
    if (inFlight || document.visibilityState === 'hidden') {
      return
    }

    inFlight = true

    const body = new URLSearchParams()
    body.append('page', page)
    body.append('submitted', submitted ? '1' : '0')

    axios.post('/precog/ping', body)
      .catch(() => {})
      .then(() => { inFlight = false })
  }

  beat()
  timer = setInterval(beat, INTERVAL)

  // Coming back to the tab should put the halo back promptly rather than at the
  // next scheduled beat, since the customer is visibly active again.
  const onVisible = () => {
    if (document.visibilityState === 'visible') {
      beat()
    }
  }
  document.addEventListener('visibilitychange', onVisible)

  const stop = () => {
    clearInterval(timer)
    document.removeEventListener('visibilitychange', onVisible)
  }

  return {
    submitted: () => {
      submitted = true
      beat()
    },
    stop,
  }
}

/**
 * Starts the heartbeat if this deployment wants it.
 *
 * The flag is read off the page rather than fetched, so a tenant with the layer
 * turned off makes no requests at all.
 *
 * @param {'cart'|'checkout'|'payment'} page
 * @param {HTMLElement|null} dataEl element carrying `data-precog-enabled`
 */
export function startPrecogPingIfEnabled(page, dataEl) {

  if (!dataEl || dataEl.dataset.precogEnabled !== 'true') {
    return null
  }

  return startPrecogPing(page)
}
