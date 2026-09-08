/**
 * Parsing/serialization helpers for the Sentry/Datadog-like search query
 * string used by <SearchQueryBar>, e.g. `date:2026-09-08 -state:cancelled foo`.
 *
 * Grammar (whitespace-separated tokens, values may be quoted to include spaces):
 *   key:value    -> included filter
 *   -key:value   -> excluded filter
 *   "some text"  -> free-text term
 *   foo          -> free-text term
 *
 * This mirrors src/Utils/SearchQuery/SearchQueryParser.php, so keep both in sync.
 */

const KEY_RE = /^([a-zA-Z_][a-zA-Z0-9_]*):(.+)$/

/**
 * Splits a query string on whitespace, honoring single/double-quoted
 * substrings (which may appear anywhere within a token, e.g. `key:"a b"`).
 * @param {string} query
 * @returns {string[]}
 */
export function tokenize(query) {
  const tokens = []
  let current = ''
  let quoteChar = null

  for (let i = 0; i < query.length; i++) {
    const char = query[i]

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
 * @returns {{ raw: string, isFilter: boolean, key?: string, value?: string, exclude: boolean }}
 */
export function parseToken(raw) {
  let exclude = false
  let token = raw

  if (token.startsWith('-') && token.length > 1) {
    exclude = true
    token = token.slice(1)
  }

  const match = token.match(KEY_RE)
  if (match) {
    return { raw, isFilter: true, key: match[1], value: match[2], exclude }
  }

  return { raw, isFilter: false, exclude }
}

/**
 * @param {string} query
 * @returns {ReturnType<typeof parseToken>[]}
 */
export function parseQuery(query) {
  return tokenize(query || '').map(parseToken)
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
 * @param {{ key: string, value: string, exclude?: boolean }} filter
 * @returns {string}
 */
export function serializeFilterToken({ key, value, exclude = false }) {
  return `${exclude ? '-' : ''}${key}:${quoteIfNeeded(String(value))}`
}

/**
 * Finds the token (and its start/end offsets within `text`) that the cursor
 * is currently positioned in/at, so autocomplete can work out what the user
 * is typing.
 * @param {string} text
 * @param {number} cursor
 */
export function getTokenAtCursor(text, cursor) {
  // Find token boundaries: whitespace not inside quotes.
  let start = cursor
  let end = cursor
  let quoteChar = null

  // Walk backwards to find the start of the current token.
  for (let i = cursor - 1; i >= 0; i--) {
    const char = text[i]
    if (char === '"' || char === "'") {
      quoteChar = quoteChar === char ? null : char
    }
    if (!quoteChar && /\s/.test(char)) {
      break
    }
    start = i
  }

  // Walk forward to find the end of the current token.
  quoteChar = null
  for (let i = cursor; i < text.length; i++) {
    const char = text[i]
    if (!quoteChar && /\s/.test(char)) {
      break
    }
    if (char === '"' || char === "'") {
      quoteChar = quoteChar === char ? null : char
    }
    end = i + 1
  }

  const raw = text.slice(start, end)

  return { raw, start, end, ...parseToken(raw) }
}

/**
 * Replaces the token at the cursor with `replacement`, appending a trailing
 * space, and returns the new text plus the cursor position after it.
 * @param {string} text
 * @param {number} cursor
 * @param {string} replacement
 */
export function replaceTokenAtCursor(text, cursor, replacement) {
  const { start, end } = getTokenAtCursor(text, cursor)
  const before = text.slice(0, start)
  const after = text.slice(end)
  const insertion = `${replacement} `

  return {
    text: `${before}${insertion}${after}`,
    cursor: before.length + insertion.length,
  }
}
