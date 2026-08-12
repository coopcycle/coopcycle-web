/*
 * Requests still in flight when the page goes away are aborted by the browser, and the
 * fetch they came from rejects. Live components are loaded lazily, so a listing page
 * regularly has several of them pending: each restaurant card POSTs to
 * /_components/FulfillmentBadge. The live component bundle does not catch that
 * rejection, so navigating away leaves an uncaught rejection behind.
 *
 * That is noise rather than an error — the request did not fail, the document it
 * belonged to simply stopped existing. It pollutes the console and error reporting,
 * and Cypress turns any uncaught exception into a test failure.
 *
 * Only rejections that happen while the page is unloading are swallowed, so a genuine
 * network failure on a live page is still reported.
 */

let isUnloading = false;

const markAsUnloading = () => {
  isUnloading = true;
};

// "pagehide" also covers the back/forward cache, "beforeunload" fires earlier
window.addEventListener('pagehide', markAsUnloading);
window.addEventListener('beforeunload', markAsUnloading);

const isAbortedRequest = reason => {
  if (!reason) {
    return false;
  }

  // fetch() rejects with an AbortError when the request is cancelled, and with a
  // TypeError when the connection goes away; the message is browser specific.
  return (
    reason.name === 'AbortError' ||
    (reason instanceof TypeError &&
      /Failed to fetch|NetworkError|Load failed|network error/i.test(
        reason.message ?? '',
      ))
  );
};

window.addEventListener('unhandledrejection', event => {
  if (isUnloading && isAbortedRequest(event.reason)) {
    event.preventDefault();
  }
});
