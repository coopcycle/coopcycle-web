import {
  MatrixAxis,
  MatrixAxisEntry,
  MatrixAxisVariable,
} from '../../../api/types';

export const NUMERIC_VARIABLES: MatrixAxisVariable[] = [
  'packages.totalVolumeUnits()',
  'delivery.packages.totalVolumeUnits()',
  'weight',
  'distance',
  'order.itemsTotal',
];

export const ENUMERATED_VARIABLES: MatrixAxisVariable[] = ['zone', 'time_slot'];

export const AXIS_VARIABLES: MatrixAxisVariable[] = [
  ...NUMERIC_VARIABLES,
  ...ENUMERATED_VARIABLES,
];

/*
  Variable names contain dots, which i18next reads as key separators, so the labels
  are looked up through a static map rather than built by interpolation.
*/
export const VARIABLE_LABEL_KEYS: Record<MatrixAxisVariable, string> = {
  'packages.totalVolumeUnits()': 'PRICING_MATRIX_VARIABLE_VOLUME_UNITS',
  'delivery.packages.totalVolumeUnits()':
    'PRICING_MATRIX_VARIABLE_DELIVERY_VOLUME_UNITS',
  weight: 'PRICING_MATRIX_VARIABLE_WEIGHT',
  distance: 'PRICING_MATRIX_VARIABLE_DISTANCE',
  'order.itemsTotal': 'PRICING_MATRIX_VARIABLE_ORDER_ITEMS_TOTAL',
  zone: 'PRICING_MATRIX_VARIABLE_ZONE',
  time_slot: 'PRICING_MATRIX_VARIABLE_TIME_SLOT',
};

export const isNumeric = (variable: MatrixAxisVariable): boolean =>
  NUMERIC_VARIABLES.includes(variable);

/*
  Bounds are stored in the unit the pricing engine works in: grams, meters and cents
  are whole numbers there, while the editor shows kilos, kilometers and euros.
*/
const FACTORS: Partial<Record<MatrixAxisVariable, number>> = {
  weight: 1000,
  distance: 1000,
  'order.itemsTotal': 100,
};

export const factorFor = (variable: MatrixAxisVariable): number =>
  FACTORS[variable] ?? 1;

export const toDisplayBound = (
  value: number | null | undefined,
  variable: MatrixAxisVariable,
): number | null =>
  value === null || value === undefined ? null : value / factorFor(variable);

export const toStoredBound = (
  value: number | null | undefined,
  variable: MatrixAxisVariable,
): number | null =>
  value === null || value === undefined
    ? null
    : Math.round(value * factorFor(variable));

export const entryLabel = (entry: MatrixAxisEntry): string =>
  entry.label && entry.label.trim() !== '' ? entry.label : entry.key;

export type AxisWarning = {
  type: 'gap' | 'overlap' | 'duplicate' | 'incomplete';
  message: string;
};

/*
  Gaps and overlaps are reported, never blocked.

  Under the 'map' strategy every matching rule adds up, so two entries matching the
  same value charge twice, and a value matching none charges nothing. Both are
  usually mistakes, but not always: zones may overlap on purpose, and a grid can be
  deliberately partial. Only the cases we can state with certainty are reported.
*/
export const findAxisWarnings = (
  axis: MatrixAxis,
  t: (key: string, values?: Record<string, unknown>) => string,
): AxisWarning[] => {
  const warnings: AxisWarning[] = [];

  if (isNumeric(axis.variable)) {
    const bounded = axis.entries.filter(
      entry =>
        (entry.min !== null && entry.min !== undefined) ||
        (entry.max !== null && entry.max !== undefined),
    );

    if (bounded.length !== axis.entries.length) {
      warnings.push({
        type: 'incomplete',
        message: t('PRICING_MATRIX_WARNING_ENTRY_WITHOUT_BOUND'),
      });
    }

    const ranges = bounded
      .map(entry => ({
        entry,
        min: entry.min ?? Number.NEGATIVE_INFINITY,
        max: entry.max ?? Number.POSITIVE_INFINITY,
      }))
      .sort((a, b) => a.min - b.min);

    for (let i = 1; i < ranges.length; i++) {
      const previous = ranges[i - 1];
      const current = ranges[i];

      if (current.min <= previous.max) {
        warnings.push({
          type: 'overlap',
          message: t('PRICING_MATRIX_WARNING_OVERLAP', {
            first: entryLabel(previous.entry),
            second: entryLabel(current.entry),
          }),
        });
      } else if (current.min > previous.max + 1) {
        warnings.push({
          type: 'gap',
          message: t('PRICING_MATRIX_WARNING_GAP', {
            first: entryLabel(previous.entry),
            second: entryLabel(current.entry),
          }),
        });
      }
    }

    return warnings;
  }

  // Zones can overlap geographically, which no check here can see: only an entry
  // used twice is certain. The rest is the admin's call.
  const seen = new Map<string, MatrixAxisEntry>();

  axis.entries.forEach(entry => {
    if (!entry.value) {
      warnings.push({
        type: 'incomplete',
        message: t('PRICING_MATRIX_WARNING_ENTRY_WITHOUT_VALUE'),
      });
      return;
    }

    if (seen.has(entry.value)) {
      warnings.push({
        type: 'duplicate',
        message: t('PRICING_MATRIX_WARNING_DUPLICATE', {
          value: entry.value,
        }),
      });
    }

    seen.set(entry.value, entry);
  });

  return warnings;
};

export const cellKey = (rowKey: string, columnKey: string): string =>
  `${rowKey}:${columnKey}`;
