import {
  getTokenAtCursor,
  parseQuery,
  replaceTokenAtCursor,
  serializeFilterToken,
  tokenize,
} from '../queryString'

describe('tokenize', () => {
  it('splits on whitespace', () => {
    expect(tokenize('foo bar   baz')).toEqual(['foo', 'bar', 'baz'])
  })

  it('honors quoted substrings within a token', () => {
    expect(tokenize('owner:"Colis prompto" foo')).toEqual(['owner:Colis prompto', 'foo'])
  })

  it('returns an empty array for an empty string', () => {
    expect(tokenize('')).toEqual([])
  })
})

describe('parseQuery', () => {
  it('parses filters and free-text terms', () => {
    const tokens = parseQuery('date:2026-09-08 -state:cancelled foo@example.com')

    expect(tokens).toEqual([
      { raw: 'date:2026-09-08', isFilter: true, key: 'date', value: '2026-09-08', exclude: false },
      { raw: '-state:cancelled', isFilter: true, key: 'state', value: 'cancelled', exclude: true },
      { raw: 'foo@example.com', isFilter: false, exclude: false },
    ])
  })

  it('treats a colon without a value as a free-text term', () => {
    expect(parseQuery('date:')).toEqual([
      { raw: 'date:', isFilter: false, exclude: false },
    ])
  })
})

describe('serializeFilterToken', () => {
  it('serializes an included filter', () => {
    expect(serializeFilterToken({ key: 'state', value: 'fulfilled' })).toBe('state:fulfilled')
  })

  it('serializes an excluded filter', () => {
    expect(serializeFilterToken({ key: 'state', value: 'cancelled', exclude: true })).toBe('-state:cancelled')
  })

  it('quotes values containing spaces', () => {
    expect(serializeFilterToken({ key: 'owner', value: 'Colis prompto' })).toBe('owner:"Colis prompto"')
  })
})

describe('getTokenAtCursor', () => {
  it('finds the token under the cursor', () => {
    const text = 'date:2026-09-08 state:fulfilled'
    // cursor right after "state:ful"
    const cursor = text.indexOf('state:fulfilled') + 'state:ful'.length
    const token = getTokenAtCursor(text, cursor)

    expect(token.raw).toBe('state:fulfilled')
    expect(token.key).toBe('state')
    expect(token.value).toBe('fulfilled')
  })

  it('finds an empty token when the cursor sits between two spaces', () => {
    const text = 'foo  bar'
    const token = getTokenAtCursor(text, 4)

    expect(token.raw).toBe('')
  })

  it('recognizes a bare "key:" with no value yet as a filter-in-progress', () => {
    // Unlike parseQuery(), the live cursor token must recognize this
    // immediately (before any value is typed) so autocomplete can react -
    // e.g. showing a date picker as soon as the user types "date:".
    const text = 'date:'
    const token = getTokenAtCursor(text, text.length)

    expect(token.isFilter).toBe(true)
    expect(token.key).toBe('date')
    expect(token.value).toBe('')
  })
})

describe('replaceTokenAtCursor', () => {
  it('replaces the current token and appends a trailing space by default', () => {
    const text = 'date:2026-09-08 stat'
    const cursor = text.length
    const { text: newText, cursor: newCursor } = replaceTokenAtCursor(text, cursor, 'state:')

    expect(newText).toBe('date:2026-09-08 state: ')
    expect(newCursor).toBe(newText.length)
  })

  it('does not append a trailing space when appendSpace is false, keeping the cursor inside the token', () => {
    const text = 'stat'
    const cursor = text.length
    const { text: newText, cursor: newCursor } = replaceTokenAtCursor(text, cursor, 'state:', { appendSpace: false })

    expect(newText).toBe('state:')
    expect(newCursor).toBe(newText.length)

    // The cursor must still be recognized as inside the "state:" filter token.
    const token = getTokenAtCursor(newText, newCursor)
    expect(token.isFilter).toBe(true)
    expect(token.key).toBe('state')
    expect(token.value).toBe('')
  })
})
