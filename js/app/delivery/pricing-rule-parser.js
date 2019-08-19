const zoneRegexp = /(in_zone|out_zone)\(([^,]+), ['|"](.+)['|"]\)/
const diffDaysRegexp = /diff_days\(pickup\) (<|>|==) ([\d]+)/
const vehicleRegexp = /(vehicle) == "(cargo_bike|bike)"/
const inRegexp = /([\w]+) in ([\d]+)\.\.([\d]+)/
const comparatorRegexp = /([\w]+) (<|>) ([\d]+)/

const parseToken = token => {

  const zoneTest = zoneRegexp.exec(token)
  if (zoneTest) {
    return {
      left: zoneTest[2],
      operator: zoneTest[1],
      right: zoneTest[3]
    }
  }

  const diffDaysTest = diffDaysRegexp.exec(token)
  if (diffDaysTest) {
    return {
      left: 'diff_days(pickup)',
      operator: diffDaysTest[1],
      right: diffDaysTest[2]
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

export default expression =>
  expression.split(' and ').map(token => token.trim()).map(token => parseToken(token))
