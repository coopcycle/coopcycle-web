export const DEFAULT_SHIFT_TYPE_COLORS: Record<string, string> = {
  drive: '#ffadad',
  dispatch: '#a0c4ff',
  admin: '#fdffb6',
};

const FALLBACKS = ['#caffbf', '#ffd6a5', '#bdb2ff', '#ffc6ff', '#9bf6ff'];

/**
 * @param overrides Custom colors configured in the shift planning settings,
 * takes precedence over the built-in defaults
 */
export function shiftTypeColor(
  type: string,
  overrides?: Record<string, string>,
): string {
  if (overrides?.[type]) {
    return overrides[type];
  }

  if (DEFAULT_SHIFT_TYPE_COLORS[type]) {
    return DEFAULT_SHIFT_TYPE_COLORS[type];
  }

  let hash = 0;
  for (let i = 0; i < type.length; i++) {
    hash = (hash * 31 + type.charCodeAt(i)) | 0;
  }

  return FALLBACKS[Math.abs(hash) % FALLBACKS.length];
}
