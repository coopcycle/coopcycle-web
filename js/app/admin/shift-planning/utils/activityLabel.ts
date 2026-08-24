import { TFunction } from 'i18next';
import { ShiftActivity } from '../../../api/types';

// The built-in activities ship with a translation (looked up by slug, which
// never changes); anything else is a dispatcher-authored custom activity and
// has no translation, so it's shown as entered.
const DEFAULT_ACTIVITY_TRANSLATION_KEYS: Record<string, string> = {
  delivery: 'SHIFT_ACTIVITY_DEFAULT_DELIVERY',
  dispatch: 'SHIFT_ACTIVITY_DEFAULT_DISPATCH',
  administration: 'SHIFT_ACTIVITY_DEFAULT_ADMINISTRATION',
};

function translate(slug: string, storedLabel: string, t: TFunction): string {
  const key = DEFAULT_ACTIVITY_TRANSLATION_KEYS[slug];
  return key ? t(key, { defaultValue: storedLabel }) : storedLabel;
}

/**
 * Shifts store the activity slug directly (not an IRI), so displaying the
 * nice label always requires a lookup against the activity catalog. Falls
 * back to the raw slug if the activity has since been deleted.
 */
export function activityLabel(
  slug: string,
  activities: ShiftActivity[] | undefined,
  t: TFunction,
): string {
  const activity = activities?.find(a => a.slug === slug);
  return activity ? translate(activity.slug, activity.label, t) : slug;
}

export function activityDisplayLabel(
  activity: ShiftActivity,
  t: TFunction,
): string {
  return translate(activity.slug, activity.label, t);
}
