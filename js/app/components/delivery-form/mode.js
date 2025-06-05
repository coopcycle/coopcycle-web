export const Mode = {
  DELIVERY_CREATE: "delivery_create",
  DELIVERY_UPDATE: "delivery_update",
  RECURRENCE_RULE_UPDATE: "recurrence_rule_update",
};

export function modeIn(currentMode, modes) {
  return modes.includes(currentMode);
}
