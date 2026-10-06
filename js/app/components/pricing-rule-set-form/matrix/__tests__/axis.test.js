import {
  findAxisWarnings,
  isNumeric,
  toDisplayBound,
  toStoredBound,
} from '../axis';

// The warnings are what the admin reads, so assert on the kind, not the wording
const t = key => key;

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
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['overlap']);
    });

    it('reports entries that have no bound yet', () => {
      const warnings = findAxisWarnings(
        numericAxis([{ key: 'a', label: 'S' }]),
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
        t,
      );

      expect(warnings).toEqual([]);
    });
  });

  describe('enumerated axis warnings', () => {
    it('accepts distinct values', () => {
      const warnings = findAxisWarnings(
        zoneAxis([
          { key: 'a', value: 'Z1' },
          { key: 'b', value: 'Z2' },
        ]),
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
        t,
      );

      expect(warnings.map(warning => warning.type)).toEqual(['duplicate']);
    });

    it('reports entries that have no value yet', () => {
      const warnings = findAxisWarnings(zoneAxis([{ key: 'a' }]), t);

      expect(warnings.map(warning => warning.type)).toEqual(['incomplete']);
    });
  });
});
