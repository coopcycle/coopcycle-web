import {
  canonicalizeToken,
  hasUnterminatedQuote,
  isBareKeyToken,
  isGroupValue,
  parseGroupValues,
  parseLiveToken,
  parseQuery,
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
})
