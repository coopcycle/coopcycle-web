import { centsInputNumberProps, amountInputNumberProps, isNilOrEmpty } from '../format';

describe('isNilOrEmpty', () => {
  it.each([
    ['null', null, true],
    ['undefined', undefined, true],
    ['empty string', '', true],
    ['zero', 0, false],
    ['false', false, false],
    ['non-empty string', 'x', false],
    ['object', {}, false],
    ['array', [], false],
  ])('%s -> %s', (_label, input, expected) => {
    expect(isNilOrEmpty(input)).toBe(expected);
  });
});

describe('centsInputNumberProps', () => {
  describe('parser', () => {
    it.each([
      ['period as decimal separator', '2.5', 250],
      ['comma as decimal separator', '2,5', 250],
      ['empty string', '', 0],
      ['null', null, 0],
      ['undefined', undefined, 0],
      ['garbage', 'abc', 0],
      ['integer', '5', 500],
      ['three decimals rounds down', '2.123', 212],
      ['three decimals rounds up', '2.999', 300],
      ['negative', '-2.5', -250],
      ['negative with comma', '-2,5', -250],
      ['negative with leading whitespace', '  -5', -500],
    ])('returns expected cents for %s', (_label, input, expected) => {
      expect(centsInputNumberProps.parser(input)).toBe(expected);
    });
  });

  describe('formatter', () => {
    it.each([
      ['undefined', undefined, ''],
      ['null', null, ''],
      ['empty string', '', ''],
      ['NaN', 'abc', ''],
      ['integer cents', 500, '5.00'],
      ['decimal cents', 250, '2.50'],
      ['zero', 0, '0.00'],
      ['negative', -250, '-2.50'],
    ])('formats %s as %s', (_label, input, expected) => {
      expect(centsInputNumberProps.formatter(input)).toBe(expected);
    });

    it('returns the raw input while user is typing (no reformat, no cursor jump)', () => {
      const info = { userTyping: true, input: '2,5' };
      expect(centsInputNumberProps.formatter(250, info)).toBe('2,5');
      expect(centsInputNumberProps.formatter(undefined, info)).toBe('2,5');
    });

    it('formats normally when user is not typing', () => {
      const info = { userTyping: false, input: '2.50' };
      expect(centsInputNumberProps.formatter(250, info)).toBe('2.50');
    });
  });

  describe('round-trip', () => {
    it('parses what the formatter outputs', () => {
      expect(centsInputNumberProps.parser(centsInputNumberProps.formatter(250))).toBe(250);
      expect(centsInputNumberProps.parser(centsInputNumberProps.formatter(1234))).toBe(1234);
    });
  });
});

describe('amountInputNumberProps', () => {
  describe('parser', () => {
    it.each([
      ['period as decimal separator', '2.5', 2.5],
      ['comma as decimal separator', '2,5', 2.5],
      ['empty string', '', 0],
      ['null', null, 0],
      ['undefined', undefined, 0],
      ['garbage', 'abc', 0],
      ['integer', '5', 5],
      ['negative', '-2.5', -2.5],
      ['negative with comma', '-2,5', -2.5],
    ])('returns expected amount for %s', (_label, input, expected) => {
      expect(amountInputNumberProps.parser(input)).toBe(expected);
    });
  });

  describe('formatter', () => {
    it.each([
      ['undefined', undefined, ''],
      ['null', null, ''],
      ['empty string', '', ''],
      ['NaN', 'abc', ''],
      ['integer', 5, '5.00'],
      ['decimal', 2.5, '2.50'],
      ['zero', 0, '0.00'],
      ['negative', -2.5, '-2.50'],
    ])('formats %s as %s', (_label, input, expected) => {
      expect(amountInputNumberProps.formatter(input)).toBe(expected);
    });

    it('returns the raw input while user is typing (no reformat, no cursor jump)', () => {
      const info = { userTyping: true, input: '2,5' };
      expect(amountInputNumberProps.formatter(2.5, info)).toBe('2,5');
    });
  });

  describe('round-trip', () => {
    it('parses what the formatter outputs', () => {
      expect(amountInputNumberProps.parser(amountInputNumberProps.formatter(2.5))).toBe(2.5);
      expect(amountInputNumberProps.parser(amountInputNumberProps.formatter(5))).toBe(5);
    });
  });
});