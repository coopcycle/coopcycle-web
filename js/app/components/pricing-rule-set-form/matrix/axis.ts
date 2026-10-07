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

export type AxisKind = 'row' | 'column';

type Translate = (key: string, values?: Record<string, unknown>) => string;

/*
  A row or a column is named by where it sits, not by its key: the key is a uuid,
  which means nothing to whoever is reading the warning. The label is appended when
  there is one, since the grid may not be filled in yet.
*/
const describeEntry = (
  entry: MatrixAxisEntry,
  index: number,
  kind: AxisKind,
  t: Translate,
): string => {
  const position = t(
    kind === 'row'
      ? 'PRICING_MATRIX_ROW_POSITION'
      : 'PRICING_MATRIX_COLUMN_POSITION',
    { index: index + 1 },
  );

  return entry.label && entry.label.trim() !== ''
    ? t('PRICING_MATRIX_ENTRY_LABELLED', { position, label: entry.label.trim() })
    : position;
};

export type AxisWarning = {
  type: 'gap' | 'overlap' | 'duplicate' | 'incomplete';
  message: string;
  // The rows or columns the warning is about, so the grid can point at them
  entryKeys: string[];
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
  kind: AxisKind,
  t: Translate,
): AxisWarning[] => {
  const warnings: AxisWarning[] = [];

  if (isNumeric(axis.variable)) {
    const bounded: {
      entry: MatrixAxisEntry;
      index: number;
      min: number;
      max: number;
    }[] = [];

    axis.entries.forEach((entry, index) => {
      const hasMin = entry.min !== null && entry.min !== undefined;
      const hasMax = entry.max !== null && entry.max !== undefined;

      if (!hasMin && !hasMax) {
        warnings.push({
          type: 'incomplete',
          message: t('PRICING_MATRIX_WARNING_ENTRY_WITHOUT_BOUND', {
            entry: describeEntry(entry, index, kind, t),
          }),
          entryKeys: [entry.key],
        });
        return;
      }

      bounded.push({
        entry,
        index,
        min: entry.min ?? Number.NEGATIVE_INFINITY,
        max: entry.max ?? Number.POSITIVE_INFINITY,
      });
    });

    const ranges = [...bounded].sort((a, b) => a.min - b.min);

    for (let i = 1; i < ranges.length; i++) {
      const previous = ranges[i - 1];
      const current = ranges[i];

      const first = describeEntry(previous.entry, previous.index, kind, t);
      const second = describeEntry(current.entry, current.index, kind, t);

      if (current.min <= previous.max) {
        warnings.push({
          type: 'overlap',
          message: t('PRICING_MATRIX_WARNING_OVERLAP', { first, second }),
          entryKeys: [previous.entry.key, current.entry.key],
        });
      } else if (current.min > previous.max + 1) {
        warnings.push({
          type: 'gap',
          message: t('PRICING_MATRIX_WARNING_GAP', { first, second }),
          entryKeys: [previous.entry.key, current.entry.key],
        });
      }
    }

    return warnings;
  }

  // Zones can overlap geographically, which no check here can see: only an entry
  // used twice is certain. The rest is the admin's call.
  const seen = new Map<string, { entry: MatrixAxisEntry; index: number }>();

  axis.entries.forEach((entry, index) => {
    if (!entry.value) {
      warnings.push({
        type: 'incomplete',
        message: t('PRICING_MATRIX_WARNING_ENTRY_WITHOUT_VALUE', {
          entry: describeEntry(entry, index, kind, t),
        }),
        entryKeys: [entry.key],
      });
      return;
    }

    const previous = seen.get(entry.value);

    if (previous) {
      warnings.push({
        type: 'duplicate',
        message: t('PRICING_MATRIX_WARNING_DUPLICATE', {
          first: describeEntry(previous.entry, previous.index, kind, t),
          second: describeEntry(entry, index, kind, t),
        }),
        entryKeys: [previous.entry.key, entry.key],
      });
      return;
    }

    seen.set(entry.value, { entry, index });
  });

  return warnings;
};

export const cellKey = (rowKey: string, columnKey: string): string =>
  `${rowKey}:${columnKey}`;

/*
  Entries start out blank: the wizard asks how many rows and columns the grid has,
  and they are labelled and bounded directly in the grid afterwards.
*/
export const createEntries = (
  count: number,
  makeKey: () => string,
): MatrixAxisEntry[] =>
  Array.from({ length: count }, () => ({ key: makeKey(), label: '' }));
