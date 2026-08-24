import { ShiftActivity } from '../../../api/types';

const FALLBACKS = ['#caffbf', '#ffd6a5', '#bdb2ff', '#ffc6ff', '#9bf6ff'];

/**
 * Deterministic color derived from the slug, used when an activity has no
 * color of its own yet (e.g. the "new activity" form, or a slug that no
 * longer resolves to an activity in the catalog).
 */
export function shiftTypeColor(slug: string): string {
  let hash = 0;
  for (let i = 0; i < slug.length; i++) {
    hash = (hash * 31 + slug.charCodeAt(i)) | 0;
  }

  return FALLBACKS[Math.abs(hash) % FALLBACKS.length];
}

/**
 * The color to render for a shift's activity: the color stored on the
 * ShiftActivity itself if set, otherwise the deterministic fallback.
 */
export function activityColor(
  slug: string,
  activities: ShiftActivity[] | undefined,
): string {
  const activity = activities?.find(a => a.slug === slug);

  return activity?.color || shiftTypeColor(slug);
}
