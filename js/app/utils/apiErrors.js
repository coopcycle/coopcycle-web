/*
 * Helpers to extract human readable messages out of API Platform errors,
 * as returned by the httpClient (axios errors).
 */

export function getApiErrorData(error) {
  return error?.response?.data || error?.data || null
}

export function getApiViolations(error) {
  const violations = getApiErrorData(error)?.violations
  return Array.isArray(violations) ? violations : []
}

/*
 * Maps constraint violations to an object usable with Formik's setErrors(),
 * i.e { propertyPath: message }
 */
export function getApiFieldErrors(error) {
  return getApiViolations(error).reduce((acc, violation) => {
    if (violation.propertyPath && violation.message) {
      acc[violation.propertyPath] = violation.message
    }
    return acc
  }, {})
}

/*
 * Returns a single message describing the error, or null when the error
 * carries no usable description.
 */
export function getApiErrorMessage(error) {
  const data = getApiErrorData(error)

  const violationMessage = getApiViolations(error)
    .map(violation => violation.message)
    .filter(Boolean)
    .join('\n')

  return (
    violationMessage ||
    data?.['hydra:description'] ||
    data?.detail ||
    data?.message ||
    error?.message ||
    null
  )
}
