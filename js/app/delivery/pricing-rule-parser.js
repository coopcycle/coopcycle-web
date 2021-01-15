const zoneRegexp = /(in_zone|out_zone)\(([^,]+), ['|"](.+)['|"]\)/
const diffDaysRegexp = /diff_days\(pickup\) (<|>|==) ([\d]+)/
const diffHoursRegexp = /diff_hours\(pickup\) (<|>|==) ([\d]+)/
const vehicleRegexp = /(vehicle)\s+== "(cargo_bike|bike)"/
const inRegexp = /([\w.]+) in ([\d]+)\.\.([\d]+)/
const comparatorRegexp = /([\w.]+) (<|>) ([\d]+)/
const doorstepDropoffRegexp = /(dropoff.doorstep)\s+== (true|false)/
const packagesContainsAtLeastOneRegexp = /packages\.containsAtLeastOne\(['|"](.+)['|"]\)/

const parseToken = token => {

  const zoneTest = zoneRegexp.exec(token)
  if (zoneTest) {
    return {
      left: zoneTest[2],
      operator: zoneTest[1],
      right: zoneTest[3]
    }
  }

  const packagesContainsAtLeastOneTest = packagesContainsAtLeastOneRegexp.exec(token)
  if (packagesContainsAtLeastOneTest) {
    return {
      left: 'packages',
      operator: 'containsAtLeastOne',
      right: packagesContainsAtLeastOneTest[1],
    }
  }

  const diffDaysTest = diffDaysRegexp.exec(token)
  if (diffDaysTest) {
    return {
      left: 'diff_days(pickup)',
      operator: diffDaysTest[1],
      right: parseInt(diffDaysTest[2])
    }
  }

  const diffHoursTest = diffHoursRegexp.exec(token)
  if (diffHoursTest) {
    return {
      left: 'diff_hours(pickup)',
      operator: diffHoursTest[1],
      right: parseInt(diffHoursTest[2], 10)
    }
  }

  const vehicleTest = vehicleRegexp.exec(token)
  if (vehicleTest) {
    return {
      left: vehicleTest[1],
      operator: '==',
      right: vehicleTest[2]
    }
  }

  const doorstepDropoffTest = doorstepDropoffRegexp.exec(token)
  if (doorstepDropoffTest) {
    return {
      left: doorstepDropoffTest[1],
      operator: '==',
      right: doorstepDropoffTest[2]
    }
  }

  const inRegexpTest = inRegexp.exec(token)
  if (inRegexpTest) {
    return {
      left: inRegexpTest[1],
      operator: 'in',
      right: [
        parseInt(inRegexpTest[2], 10),
        parseInt(inRegexpTest[3], 10)
      ]
    }
  }

  const comparatorTest = comparatorRegexp.exec(token)
  if (comparatorTest) {
    return {
      left: comparatorTest[1],
      operator: comparatorTest[2],
      right: parseInt(comparatorTest[3], 10)
    }
  }
}

export default expression => {

  const lines = expression.split(' and ').map(token => token.trim())

  if (lines.length === 1 && !lines[0]) {
    return []
  }

  return lines.map(token => parseToken(token))
}
