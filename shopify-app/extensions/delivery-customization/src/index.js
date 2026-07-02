/**
 * CoopCycle Delivery Zone Filter
 *
 * Hides any delivery option whose title contains "coopcycle" (case-insensitive)
 * when the customer's postal code is not in the cooperative's delivery zone.
 *
 * Postal codes are stored in a shop metafield (set by CoopCycle on the store
 * settings page):
 *   namespace : "coopcycle"
 *   key       : "delivery_postal_codes"
 *   value     : JSON array of strings, e.g. ["75010","75011","75012"]
 *
 * If the metafield is absent or empty every option is shown (fail-open).
 */

const RATE_TITLE_KEYWORD = "coopcycle";

/**
 * @param {Object} input - Shopify Function input (shape defined by index.graphql)
 * @returns {{ operations: Array }}
 */
export function run(input) {
  // --- Parse the allowed postal codes ----------------------------------
  const raw = input.shop?.metafield?.value ?? "[]";
  let allowedCodes;

  try {
    allowedCodes = JSON.parse(raw);
  } catch {
    // Malformed metafield: do not hide anything.
    return { operations: [] };
  }

  if (!Array.isArray(allowedCodes) || allowedCodes.length === 0) {
    // Not configured yet: do not hide anything.
    return { operations: [] };
  }

  const normalizedAllowed = allowedCodes.map(normalize);

  // --- Build hide operations -------------------------------------------
  const operations = [];

  for (const group of input.cart.deliveryGroups) {
    const zip = normalize(group.deliveryAddress?.zip ?? "");

    // Empty postal code → can't make a decision, show everything.
    if (zip === "") continue;

    if (!normalizedAllowed.includes(zip)) {
      for (const option of group.deliveryOptions) {
        if (option.title.toLowerCase().includes(RATE_TITLE_KEYWORD)) {
          operations.push({ hide: { deliveryOptionHandle: option.handle } });
        }
      }
    }
  }

  return { operations };
}

/** Strips whitespace and upper-cases a postal code for reliable comparison. */
function normalize(code) {
  return String(code).replace(/\s+/g, "").toUpperCase();
}
