import {
  findAxisWarnings,
  isNumeric,
  toDisplayBound,
  toStoredBound,
} from '../axis';

// The warnings are what the admin reads, so assert on the kind and on which rows
// they point at, not on the wording
const t = (key, values) =>
  values ? `${key}:${JSON.stringify(values)}` : key;

const numericAxis = entries => ({
  variable: 'delivery.packages.totalVolumeUnits()',
  entries,
});

const zoneAxis = entries => ({
  variable: 'zone',
  addressSource: 'task',
  entries,
});

describe('matrix axis helpers', () => {
  describe('units', () => {
    it('keeps volume units as they are', () => {
      expect(toStoredBound(3, 'delivery.packages.totalVolumeUnits()')).toEqual(3);
      expect(toDisplayBound(3, 'delivery.packages.totalVolumeUnits()')).toEqual(3);
    });

    it('converts kilos to grams and back', () => {
      expect(toStoredBound(2.5, 'weight')).toEqual(2500);
      expect(toDisplayBound(2500, 'weight')).toEqual(2.5);
    });

    it('converts euros to cents and back', () => {
      expect(toStoredBound(12.5, 'order.itemsTotal')).toEqual(1250);
      expect(toDisplayBound(1250, 'order.itemsTotal')).toEqual(12.5);
    });

    it('leaves an open bound alone', () => {
      expect(toStoredBound(null, 'weight')).toBeNull();
      expect(toDisplayBound(undefined, 'weight')).toBeNull();
    });

    it('knows which variables are numeric', () => {
      expect(isNumeric('weight')).toBe(true);
      expect(isNumeric('zone')).toBe(false);
    });
  });

  describe('numeric axis warnings', () => {
    it('accepts contiguous ranges', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: 'a', label: 'S', min: 1, max: 1 },
          { key: 'b', label: 'M', min: 2, max: 3 },
          { key: 'c', label: 'L', min: 4, max: null },
        ]),
        'row',
        t,
      );

      expect(warnings).toEqual([]);
    });

    it('reports a gap', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: 'a', label: 'S', min: 1, max: 1 },
          { key: 'b', label: 'L', min: 4, max: 6 },
        ]),
        'row',
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['gap']);
    });

    it('reports an overlap', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: 'a', label: 'S', min: 1, max: 3 },
          { key: 'b', label: 'M', min: 3, max: 6 },
        ]),
        'row',
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['overlap']);
    });

    it('reports entries that have no bound yet', () => {
      const warnings = findAxisWarnings(
        numericAxis([{ key: 'a', label: 'S' }]),
        'row',
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['incomplete']);
    });

    it('sorts the ranges before comparing them', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: 'b', label: 'M', min: 2, max: 3 },
          { key: 'a', label: 'S', min: 1, max: 1 },
        ]),
        'row',
        t,
      );

      expect(warnings).toEqual([]);
    });
  });

  describe('what a warning points at', () => {
    it('names rows by their position, never by their key', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: '9fd0a1e2-uuid', label: 'S', min: 1, max: 3 },
          { key: '2b7c4d5f-uuid', label: 'M', min: 2, max: 6 },
        ]),
        'row',
        t,
      );

      expect(warnings).toHaveLength(1);
      expect(warnings[0].message).not.toContain('uuid');
      expect(warnings[0].message).toContain('PRICING_MATRIX_WARNING_OVERLAP');
      expect(warnings[0].message).toContain('PRICING_MATRIX_ROW_POSITION');
    });

    it('carries the keys of both offending rows', () => {
      const warnings = findAxisWarnings(
        numericAxis([
          { key: 'a', label: 'S', min: 1, max: 3 },
          { key: 'b', label: 'M', min: 2, max: 6 },
        ]),
        'row',
        t,
      );

      expect(warnings[0].entryKeys).toEqual(['a', 'b']);
    });

    it('carries the key of the row that has no range', () => {
      const warnings = findAxisWarnings(
        numericAxis([{ key: 'lonely', label: 'S' }]),
        'row',
        t,
      );

      expect(warnings[0].entryKeys).toEqual(['lonely']);
    });

    it('names columns as columns', () => {
      const warnings = findAxisWarnings(
        zoneAxis([{ key: 'a' }]),
        'column',
        t,
      );

      expect(warnings[0].message).toContain('PRICING_MATRIX_COLUMN_POSITION');
    });
  });

  describe('enumerated axis warnings', () => {
    it('accepts distinct values', () => {
      const warnings = findAxisWarnings(
        zoneAxis([
          { key: 'a', value: 'Z1' },
          { key: 'b', value: 'Z2' },
        ]),
        'column',
        t,
      );

      expect(warnings).toEqual([]);
    });

    it('reports the same value used twice', () => {
      const warnings = findAxisWarnings(
        zoneAxis([
          { key: 'a', value: 'Z1' },
          { key: 'b', value: 'Z1' },
        ]),
        'column',
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['duplicate']);
    });

    it('reports entries that have no value yet', () => {
      const warnings = findAxisWarnings(zoneAxis([{ key: 'a' }]), 'column', t);

      expect(warnings.map(warning => warning.type)).toEqual(['incomplete']);
    });
  });
});
