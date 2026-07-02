const PRESETS: Record<string, string> = {
  drive: '#ffadad',
  dispatch: '#a0c4ff',
  admin: '#fdffb6',
};

const FALLBACKS = ['#caffbf', '#ffd6a5', '#bdb2ff', '#ffc6ff', '#9bf6ff'];

export function shiftTypeColor(type: string): string {
  if (PRESETS[type]) {
    return PRESETS[type];
  }

  let hash = 0;
  for (let i = 0; i < type.length; i++) {
    hash = (hash * 31 + type.charCodeAt(i)) | 0;
  }

  return FALLBACKS[Math.abs(hash) % FALLBACKS.length];
}
