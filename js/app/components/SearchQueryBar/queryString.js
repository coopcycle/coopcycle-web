/**
 * Parsing/serialization helpers for the Sentry/Datadog-like search query
 * string used by <SearchQueryBar>, e.g. `date:2026-09-08 -state:cancelled foo`.
 *
 * Grammar (whitespace-separated tokens, values may be quoted to include spaces):
 *   key:value                  -> included filter
 *   -key:value                 -> excluded filter
 *   key:(value1 OR value2)     -> included filter, matching any of the values
 *   -key:(value1 OR value2)    -> excluded filter, matching any of the values
 *   "some text"                -> free-text term
 *   foo                        -> free-text term
 *
 * The "(v1 OR v2)" group form is what a `multi: true` field (see
 * SearchQueryBar's fields doc) builds from its checkbox dropdown - a single
 * token, kept together by tokenize() below.
 *
 * This mirrors src/Utils/SearchQuery/SearchQueryParser.php, so keep both in sync.
 */

const KEY_RE = /^([a-zA-Z_][a-zA-Z0-9_]*):(.+)$/
// Same as KEY_RE, but also matches a key with no value yet (e.g. "date:").
// Used only while the user is still typing the current token (see
// SearchQueryBar's "draft"), so autocomplete can react as soon as the colon
// is typed. A key with no value isn't a "real" filter for submission
// purposes - see the PHP parser's testColonWithoutValueIsATerm - so
// parseToken()/parseQuery() default to the stricter KEY_RE above.
const LIVE_KEY_RE = /^([a-zA-Z_][a-zA-Z0-9_]*):(.*)$/

// Matches the token built up so far when it's exactly "key:" or "-key:",
// right before a "(" - i.e. the start of a "key:(v1 OR v2)" value group.
const GROUP_OPEN_RE = /^-?[a-zA-Z_][a-zA-Z0-9_]*:$/

/**
 * Splits a query string on whitespace, honoring single/double-quoted
 * substrings (which may appear anywhere within a token, e.g. `key:"a b"`),
 * and treating a "key:(...)" value group as one token regardless of the
 * whitespace inside it (e.g. `owner:("a" OR "b")` stays a single token).
 * @param {string} query
 * @returns {string[]}
 */
export function tokenize(query) {
  const tokens = []
  let current = ''
  let quoteChar = null
  let groupDepth = 0

  for (let i = 0; i < query.length; i++) {
    const char = query[i]

    if (groupDepth > 0) {
      // Inside a "key:(...)" value group: copy everything verbatim
      // (quotes included) so the " OR "-separated values can be split out
      // later - only whitespace *outside* the group acts as a boundary.
      if (quoteChar) {
        current += char
        if (char === quoteChar) {
          quoteChar = null
        }
        continue
      }
      if (char === '"' || char === "'") {
        quoteChar = char
        current += char
        continue
      }
      if (char === '(') {
        groupDepth += 1
      } else if (char === ')') {
        groupDepth -= 1
      }
      current += char
      continue
    }

    if (quoteChar) {
      if (char === quoteChar) {
        quoteChar = null
      } else {
        current += char
      }
      continue
    }

    if (char === '"' || char === "'") {
      quoteChar = char
      continue
    }

    if (char === '(' && GROUP_OPEN_RE.test(current)) {
      groupDepth = 1
      current += char
      continue
    }

    if (/\s/.test(char)) {
      if (current !== '') {
        tokens.push(current)
        current = ''
      }
      continue
    }

    current += char
  }

  if (current !== '') {
    tokens.push(current)
  }

  return tokens
}

/**
 * @param {string} raw a single token, as returned by tokenize()
 * @param {RegExp} keyRe internal - pass LIVE_KEY_RE to also match a bare "key:"
 * @returns {{ raw: string, isFilter: boolean, key?: string, value?: string, exclude: boolean }}
 */
export function parseToken(raw, keyRe = KEY_RE) {
  let exclude = false
  let token = raw

  if (token.startsWith('-') && token.length > 1) {
    exclude = true
    token = token.slice(1)
  }

  const match = token.match(keyRe)
  if (match) {
    return { raw, isFilter: true, key: match[1], value: match[2], exclude }
  }

  return { raw, isFilter: false, exclude }
}

/**
 * Parses the token currently being typed (SearchQueryBar's "draft"), which
 * may still have a bare "key:" with no value - see LIVE_KEY_RE above.
 * @param {string} raw
 */
export function parseLiveToken(raw) {
  return parseToken(raw, LIVE_KEY_RE)
}

/**
 * Whether `token` is a "key:" with nothing typed after the colon yet - e.g.
 * right after picking a field from the suggestion list, before any value is
 * typed. Not a real filter (see testColonWithoutValueIsATerm), so it isn't
 * safe to commit as-is: doing so would silently turn it into a free-text
 * tag literally reading "key:" (canonicalizeToken()/parseToken() only see a
 * string that fails to match a real filter).
 * @param {string} token
 */
export function isBareKeyToken(token) {
  const live = parseLiveToken(token)
  return live.isFilter && live.value === ''
}

/**
 * @param {string} query
 * @returns {ReturnType<typeof parseToken>[]}
 */
export function parseQuery(query) {
  // Not map(parseToken) directly: Array#map passes (item, index, array), and
  // parseToken's 2nd param is keyRe - the index would silently clobber it.
  return tokenize(query || '').map(raw => parseToken(raw))
}

/**
 * Quotes a value if needed so it round-trips through tokenize() as one token.
 * @param {string} value
 */
export function quoteIfNeeded(value) {
  if (/[\s"]/.test(value)) {
    return `"${value.replace(/"/g, '')}"`
  }
  return value
}

/**
 * Whether a (already-extracted) filter value is a "(v1 OR v2)" group, as
 * opposed to a plain single value.
 * @param {string} value
 */
export function isGroupValue(value) {
  return /^\(.*\)$/.test(value)
}

/**
 * Splits a filter value into its individual values: a single-item array for
 * a plain value, or one item per value for a "(v1 OR v2)" group - honoring
 * quotes around each value, same as tokenize(). Values are returned
 * unquoted, for display/editing purposes (mirrors unquote()).
 * @param {string} value
 * @returns {string[]}
 */
export function parseGroupValues(value) {
  if (!isGroupValue(value)) {
    return [unquote(value)]
  }

  const inner = value.slice(1, -1)
  const parts = []
  let current = ''
  let quoteChar = null
  let i = 0

  while (i < inner.length) {
    const char = inner[i]

    if (quoteChar) {
      if (char === quoteChar) {
        quoteChar = null
      } else {
        current += char
      }
      i += 1
      continue
    }

    if (char === '"' || char === "'") {
      quoteChar = char
      i += 1
      continue
    }

    if (inner.slice(i, i + 4) === ' OR ') {
      parts.push(current)
      current = ''
      i += 4
      continue
    }

    current += char
    i += 1
  }
  parts.push(current)

  return parts.map(part => part.trim()).filter(part => part !== '')
}

/**
 * @param {{ key: string, value?: string, values?: string[], exclude?: boolean }} filter
 *   Pass `values` (a multi-value field's checked options) to build a
 *   "(v1 OR v2)" group - collapsed to a plain single value automatically
 *   when there's only one. Pass `value` for a plain single-value filter.
 * @returns {string}
 */
export function serializeFilterToken({ key, value, values, exclude = false }) {
  const prefix = `${exclude ? '-' : ''}${key}:`

  if (values && values.length > 0) {
    if (values.length === 1) {
      return `${prefix}${quoteIfNeeded(String(values[0]))}`
    }
    return `${prefix}(${values.map(v => quoteIfNeeded(String(v))).join(' OR ')})`
  }

  return `${prefix}${quoteIfNeeded(String(value))}`
}

/**
 * Strips one layer of matching surrounding quotes, for display purposes only
 * (e.g. rendering a committed token as a tag/pill). The canonical/submitted
 * form is untouched by this - only quoteIfNeeded() decides whether to quote.
 * @param {string} value
 */
export function unquote(value) {
  const match = value.match(/^(["'])([\s\S]*)\1$/)
  return match ? match[2] : value
}

/**
 * Whether `str` ends mid-way through an open quote (an odd number of quote
 * chars) - i.e. the user is still typing a quoted, possibly multi-word,
 * value and a space shouldn't be treated as a token boundary yet.
 * @param {string} str
 */
export function hasUnterminatedQuote(str) {
  let quoteChar = null
  for (const char of str) {
    if (quoteChar) {
      if (char === quoteChar) {
        quoteChar = null
      }
    } else if (char === '"' || char === "'") {
      quoteChar = char
    }
  }
  return quoteChar !== null
}

/**
 * Re-serializes a token that came out of tokenize() (which strips quotes)
 * back into a round-trip-safe canonical form - e.g. `owner:"a value"` for a
 * filter whose value contains whitespace, or a quoted free-text term.
 * Tokens that don't need quoting are returned unchanged. This is what makes
 * it safe to store tokenize() output directly and later re-join with " ".
 * @param {string} raw as produced by tokenize() - already dequoted
 */
export function canonicalizeToken(raw) {
  const parsed = parseToken(raw)
  if (parsed.isFilter) {
    if (isGroupValue(parsed.value)) {
      // Already round-trip-safe as-is - tokenize() preserves a group's
      // inner quoting verbatim, so re-quoting the whole thing (which would
      // treat it as one big value with spaces) would be wrong.
      return `${parsed.exclude ? '-' : ''}${parsed.key}:${parsed.value}`
    }
    return serializeFilterToken({ key: parsed.key, value: parsed.value, exclude: parsed.exclude })
  }
  return quoteIfNeeded(raw)
}
