import {
  canonicalizeToken,
  hasExcludePrefix,
  hasUnterminatedQuote,
  isBareKeyToken,
  isGroupValue,
  isRangeValue,
  parseGroupValues,
  parseRangeValue,
  parseLiveToken,
  parseQuery,
  sanitizeQuery,
  serializeFilterToken,
  tokenize,
  unquote,
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

  it('keeps a "key:(v1 OR v2)" value group as a single token', () => {
    expect(tokenize('owner:("Colis prompto" OR "Couture express") foo')).toEqual([
      'owner:("Colis prompto" OR "Couture express")', 'foo',
    ])
  })

  it('keeps an unquoted value group as a single token', () => {
    expect(tokenize('owner:(Acme OR Bistro)')).toEqual(['owner:(Acme OR Bistro)'])
  })

  it('keeps an excluded value group as a single token', () => {
    expect(tokenize('-owner:(Acme OR Bistro)')).toEqual(['-owner:(Acme OR Bistro)'])
  })

  it('keeps a "key:[from TO to]" range as a single token', () => {
    expect(tokenize('date:[2026-09-25 TO 2026-09-26] state:new')).toEqual([
      'date:[2026-09-25 TO 2026-09-26]', 'state:new',
    ])
  })

  it('keeps an excluded range as a single token', () => {
    expect(tokenize('-date:[2026-09-25 TO 2026-09-26]')).toEqual(['-date:[2026-09-25 TO 2026-09-26]'])
  })

  it('does not let one bracket type close the other', () => {
    expect(tokenize('owner:(a] b) x')).toEqual(['owner:(a] b)', 'x'])
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

describe('parseLiveToken', () => {
  it('recognizes a bare "key:" with no value yet as a filter-in-progress', () => {
    // Unlike parseQuery(), this must recognize the token immediately (before
    // any value is typed) so autocomplete can react - e.g. showing a date
    // picker as soon as the user types "date:".
    const token = parseLiveToken('date:')

    expect(token.isFilter).toBe(true)
    expect(token.key).toBe('date')
    expect(token.value).toBe('')
  })

  it('still parses a complete filter normally', () => {
    expect(parseLiveToken('-state:cancelled')).toEqual({
      raw: '-state:cancelled', isFilter: true, key: 'state', value: 'cancelled', exclude: true,
    })
  })

  it('treats plain text as a free-text term', () => {
    expect(parseLiveToken('foo')).toEqual({ raw: 'foo', isFilter: false, exclude: false })
  })
})

describe('isBareKeyToken', () => {
  it('is true for a key with no value yet', () => {
    expect(isBareKeyToken('date:')).toBe(true)
    expect(isBareKeyToken('owner:')).toBe(true)
  })

  it('is true for an excluded key with no value yet', () => {
    expect(isBareKeyToken('-owner:')).toBe(true)
  })

  it('is false once a value is typed', () => {
    expect(isBareKeyToken('date:2026-09-08')).toBe(false)
  })

  it('is false for plain free text (including a lone "-")', () => {
    expect(isBareKeyToken('foo')).toBe(false)
    expect(isBareKeyToken('-')).toBe(false)
    expect(isBareKeyToken('')).toBe(false)
  })
})

describe('isRangeValue / parseRangeValue', () => {
  it('recognizes and splits a range', () => {
    expect(isRangeValue('[2026-09-25 TO 2026-09-26]')).toBe(true)
    expect(parseRangeValue('[2026-09-25 TO 2026-09-26]')).toEqual({
      from: '2026-09-25', to: '2026-09-26',
    })
  })

  it('is not confused by a value list', () => {
    expect(isRangeValue('(a OR b)')).toBe(false)
    expect(parseRangeValue('(a OR b)')).toBeNull()
  })

  it('returns null for a plain value or a half-typed range', () => {
    expect(parseRangeValue('2026-09-25')).toBeNull()
    expect(parseRangeValue('[2026-09-25]')).toBeNull()
    expect(parseRangeValue('[ TO 2026-09-26]')).toBeNull()
  })
})

describe('sanitizeQuery', () => {
  const KEYS = ['date', 'state', 'owner']

  it('keeps filters on a known key', () => {
    expect(sanitizeQuery('date:2026-09-08 -state:cancelled', KEYS))
      .toEqual(['date:2026-09-08', '-state:cancelled'])
  })

  it('drops a malformed token from a mangled URL', () => {
    // The reported case: "?q=-state:cancelled ...:Fiducial" rendered the
    // junk as a tag that looked like a filter but filtered nothing.
    expect(sanitizeQuery('-state:cancelled ...:Fiducial', KEYS)).toEqual(['-state:cancelled'])
  })

  it('drops a filter on a key this bar does not offer', () => {
    expect(sanitizeQuery('state:new nope:whatever', KEYS)).toEqual(['state:new'])
  })

  it('drops free-text terms', () => {
    expect(sanitizeQuery('foo state:new "some text"', KEYS)).toEqual(['state:new'])
  })

  it('drops a bare "key:" with no value', () => {
    expect(sanitizeQuery('state: owner:Acme', KEYS)).toEqual(['owner:Acme'])
  })

  it('keeps a multi-value group intact', () => {
    expect(sanitizeQuery('owner:("Colis prompto" OR "Couture express") junk', KEYS))
      .toEqual(['owner:("Colis prompto" OR "Couture express")'])
  })

  it('canonicalizes what it keeps', () => {
    expect(sanitizeQuery('owner:"Colis prompto"', KEYS)).toEqual(['owner:"Colis prompto"'])
  })

  it('returns an empty array for an empty or fully invalid query', () => {
    expect(sanitizeQuery('', KEYS)).toEqual([])
    expect(sanitizeQuery(null, KEYS)).toEqual([])
    expect(sanitizeQuery('...:Fiducial', KEYS)).toEqual([])
  })
})

describe('hasExcludePrefix', () => {
  it('is true for a lone "-", which parseToken still reads as free text', () => {
    // Regression: picking a field right after typing "-" used to drop it,
    // turning the intended exclusion into a plain "contains" filter.
    expect(hasExcludePrefix('-')).toBe(true)
    expect(parseLiveToken('-').exclude).toBe(false)
  })

  it('is true once a key is being typed after the "-"', () => {
    expect(hasExcludePrefix('-own')).toBe(true)
    expect(hasExcludePrefix('-owner:Acme')).toBe(true)
  })

  it('is false without a leading "-"', () => {
    expect(hasExcludePrefix('')).toBe(false)
    expect(hasExcludePrefix('owner')).toBe(false)
    expect(hasExcludePrefix('foo-bar')).toBe(false)
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

  it('serializes several values as a "(v1 OR v2)" group', () => {
    expect(serializeFilterToken({ key: 'owner', values: ['Colis prompto', 'Couture express'] }))
      .toBe('owner:("Colis prompto" OR "Couture express")')
  })

  it('serializes an excluded group', () => {
    expect(serializeFilterToken({ key: 'owner', values: ['Acme', 'Bistro'], exclude: true }))
      .toBe('-owner:(Acme OR Bistro)')
  })

  it('collapses a single-item values array to a plain value', () => {
    expect(serializeFilterToken({ key: 'owner', values: ['Acme'] })).toBe('owner:Acme')
  })

  it('serializes a range', () => {
    expect(serializeFilterToken({ key: 'date', range: { from: '2026-09-25', to: '2026-09-26' } }))
      .toBe('date:[2026-09-25 TO 2026-09-26]')
  })

  it('serializes an excluded range', () => {
    expect(serializeFilterToken({ key: 'date', range: { from: '2026-09-25', to: '2026-09-26' }, exclude: true }))
      .toBe('-date:[2026-09-25 TO 2026-09-26]')
  })

  it('collapses a single-day range to a plain value', () => {
    expect(serializeFilterToken({ key: 'date', range: { from: '2026-09-25', to: '2026-09-25' } }))
      .toBe('date:2026-09-25')
  })
})

describe('isGroupValue', () => {
  it('is true for a "(v1 OR v2)" group', () => {
    expect(isGroupValue('(Acme OR Bistro)')).toBe(true)
  })

  it('is false for a plain value', () => {
    expect(isGroupValue('Acme')).toBe(false)
    expect(isGroupValue('"Colis prompto"')).toBe(false)
  })
})

describe('parseGroupValues', () => {
  it('splits an unquoted group into its values', () => {
    expect(parseGroupValues('(Acme OR Bistro)')).toEqual(['Acme', 'Bistro'])
  })

  it('splits a quoted group, honoring spaces within each value', () => {
    expect(parseGroupValues('("Colis prompto" OR "Couture express")')).toEqual(['Colis prompto', 'Couture express'])
  })

  it('returns a single-item array for a plain (non-group) value', () => {
    expect(parseGroupValues('Acme')).toEqual(['Acme'])
    expect(parseGroupValues('"Colis prompto"')).toEqual(['Colis prompto'])
  })
})

describe('unquote', () => {
  it('strips matching surrounding double quotes', () => {
    expect(unquote('"Colis prompto"')).toBe('Colis prompto')
  })

  it('strips matching surrounding single quotes', () => {
    expect(unquote("'Colis prompto'")).toBe('Colis prompto')
  })

  it('leaves an unquoted value untouched', () => {
    expect(unquote('cancelled')).toBe('cancelled')
  })

  it('leaves mismatched quotes untouched', () => {
    expect(unquote('"Colis prompto\'')).toBe('"Colis prompto\'')
  })
})

describe('hasUnterminatedQuote', () => {
  it('is false for a plain string', () => {
    expect(hasUnterminatedQuote('owner:Colis')).toBe(false)
  })

  it('is true right after an opening quote', () => {
    expect(hasUnterminatedQuote('owner:"Colis')).toBe(true)
  })

  it('is false once the quote is closed', () => {
    expect(hasUnterminatedQuote('owner:"Colis prompto"')).toBe(false)
  })
})

describe('canonicalizeToken', () => {
  it('leaves a simple filter token unchanged', () => {
    expect(canonicalizeToken('state:cancelled')).toBe('state:cancelled')
  })

  it('re-quotes a filter value containing spaces (as tokenize() would strip it)', () => {
    expect(canonicalizeToken('owner:Colis prompto')).toBe('owner:"Colis prompto"')
  })

  it('preserves the exclude prefix', () => {
    expect(canonicalizeToken('-owner:Colis prompto')).toBe('-owner:"Colis prompto"')
  })

  it('quotes a free-text term containing spaces', () => {
    expect(canonicalizeToken('some text')).toBe('"some text"')
  })

  it('leaves a plain free-text term unchanged', () => {
    expect(canonicalizeToken('foo')).toBe('foo')
  })

  it('round-trips a "(v1 OR v2)" value group unchanged', () => {
    expect(canonicalizeToken('owner:("Colis prompto" OR "Couture express")'))
      .toBe('owner:("Colis prompto" OR "Couture express")')
  })

  it('round-trips an excluded value group, preserving the prefix', () => {
    expect(canonicalizeToken('-owner:(Acme OR Bistro)')).toBe('-owner:(Acme OR Bistro)')
  })

  it('round-trips a range, rather than quoting it as one spaced value', () => {
    expect(canonicalizeToken('date:[2026-09-25 TO 2026-09-26]')).toBe('date:[2026-09-25 TO 2026-09-26]')
    expect(canonicalizeToken('-date:[2026-09-25 TO 2026-09-26]')).toBe('-date:[2026-09-25 TO 2026-09-26]')
  })
})
