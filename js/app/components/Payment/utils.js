export function isGuest(formOptions) {
  return formOptions && !formOptions.orderHasUser
}
