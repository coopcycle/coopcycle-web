export const Mode = {
  DELIVERY_CREATE: "delivery_create",
  DELIVERY_UPDATE: "delivery_update",
  RECURRENCE_RULE_UPDATE: "recurrence_rule_update",
} as const;

export type ModeType = typeof Mode[keyof typeof Mode];

export function modeIn(currentMode: string, modes: string[]): boolean {
  return modes.includes(currentMode);
}
