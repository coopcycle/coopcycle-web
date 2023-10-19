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

const traverseNode = (node, accumulator) => {
  if (node.attributes.operator === 'and') {
    traverseNode(node.nodes.left, accumulator)
    traverseNode(node.nodes.right, accumulator)
  } else {

    if (node.attributes.type === 2) {
      accumulator.push({
        left:     node.nodes.node.attributes.name,
        operator: node.nodes.attribute.attributes.value,
        right:    node.nodes.arguments.nodes[1].attributes.value,
      })
    } else if (node.attributes.name === 'in_zone' || node.attributes.name === 'out_zone') {
      accumulator.push({
        left:     `${node.nodes.arguments.nodes[0].nodes.node.attributes.name}.${node.nodes.arguments.nodes[0].nodes.attribute.attributes.value}`,
        operator: node.attributes.name,
        right:    node.nodes.arguments.nodes[1].attributes.value,
      })
    } else {
      if (node.nodes.left.nodes.node?.attributes.name === 'dropoff' && node.nodes.left.nodes.attribute?.attributes.value === 'doorstep') {
        accumulator.push({
          left:     `${node.nodes.left.nodes.node.attributes.name}.${node.nodes.left.nodes.attribute.attributes.value}`,
          operator: node.attributes.operator,
          right:    node.nodes.right.attributes.value,
        })
      } else if (node.nodes.left.nodes.node?.attributes.name === 'order' && node.nodes.left.nodes.attribute?.attributes.value === 'itemsTotal') {
        let $right
        if ('in' === node.attributes.operator) {
          $right = [
            node.nodes.right.nodes.left.attributes.value,
            node.nodes.right.nodes.right.attributes.value
          ]
        } else {
          $right =  node.nodes.right.attributes.value
        }
       
        accumulator.push({
          left:     `${node.nodes.left.nodes.node.attributes.name}.${node.nodes.left.nodes.attribute.attributes.value}`,
          operator: node.attributes.operator,
          right: $right,
        })

      } else if (node.nodes.left.attributes.name === 'diff_hours' || node.nodes.left.attributes.name === 'diff_days') {
        accumulator.push({
          left:     `${node.nodes.left.attributes.name}(${node.nodes.left.nodes.arguments.nodes[0].attributes.name})`,
          operator: node.attributes.operator,
          right:    node.nodes.right.attributes.value,
        })
      } else if (node.nodes.left.attributes.name === 'time_range_length') {
        let $right
        if ('in' === node.attributes.operator) {
          $right = [
            node.nodes.right.nodes.left.attributes.value,
            node.nodes.right.nodes.right.attributes.value
          ]
        } else {
          $right =  node.nodes.right.attributes.value
        }

        accumulator.push({
          left:     `time_range_length(${node.nodes.left.nodes.arguments.nodes[0].attributes.name}, 'hours')`,
          operator: node.attributes.operator,
          right:    $right,
        })
      } else if (node.nodes.left.nodes?.node?.attributes?.name === 'packages' && node.nodes.left.nodes?.attribute?.attributes?.value === 'totalVolumeUnits') {
        if (node.attributes.operator === 'in') {
          accumulator.push({
            left:     'packages.totalVolumeUnits()',
            operator: node.attributes.operator,
            right:    [
              node.nodes.right.nodes.left.attributes.value,
              node.nodes.right.nodes.right.attributes.value
            ],
          })
        } else {
          accumulator.push({
            left:     'packages.totalVolumeUnits()',
            operator: node.attributes.operator,
            right:    node.nodes.right.attributes.value,
          })
        }
      } else if ('in' === node.attributes.operator) {
        accumulator.push({
          left:     node.nodes.left.attributes.name,
          operator: node.attributes.operator,
          right:    [
            node.nodes.right.nodes.left.attributes.value,
            node.nodes.right.nodes.right.attributes.value
          ],
        })
      } else {

        let left = node.nodes.left.attributes.name
        if (node.nodes?.left?.nodes?.node?.attributes?.name === 'task' && node.nodes?.left?.nodes?.attribute?.attributes?.value === 'type') {
          left = 'task.type'
        }

        accumulator.push({
          left,
          operator: node.attributes.operator,
          right:    node.nodes.right.attributes.value,
        })
      }
    }
  }
}

export class Price {

}

export class FixedPrice extends Price {
  constructor(value) {
    super()
    this.value = value
  }
}

export class PriceRange extends Price {
  constructor(attribute, price, step, threshold) {
    super()
    this.attribute = attribute
    this.price = price
    this.step = step
    this.threshold = threshold
  }
}

export class PricePerPackage extends Price {
  constructor(packageName, unitPrice, offset, discountPrice) {
    super()
    this.packageName = packageName
    this.unitPrice = unitPrice
    this.offset = offset
    this.discountPrice = discountPrice
  }
}

export class RawPriceExpression extends Price {
  constructor(expression) {
    super()
    this.expression = expression
  }
}

export const parseAST = ast => {

  const acc = []

  traverseNode(ast.nodes, acc)

  return acc
}

const parsePriceNode = (node, expression) => {

  if (node.attributes.name === 'price_range') {

    const args = node.nodes.arguments.nodes

    const attribute = (args[0].nodes?.node?.attributes.name === 'packages' && args[0].nodes?.attribute?.attributes.value === 'totalVolumeUnits')
      ? 'packages.totalVolumeUnits()' : args[0].attributes.name
    const price     = args[1].attributes.value
    const step      = args[2].attributes.value
    const threshold = args[3].attributes.value

    return new PriceRange(attribute, price, step, threshold)
  }

  if (node.attributes.operator && node.attributes.operator === '*'
  &&  node.nodes.left?.nodes?.node?.attributes?.name === 'packages') {

    const packageName = node.nodes.left.nodes.arguments.nodes[1].attributes.value
    const unitPrice = node.nodes.right.attributes.value

    return new PricePerPackage(packageName, unitPrice)
  }

  if (node.attributes.name === 'price_per_package') {

    const args = node.nodes.arguments.nodes

    const packageName   = args[1].attributes.value
    const unitPrice     = args[2].attributes.value
    const offset        = args[3].attributes.value
    const discountPrice = args[4].attributes.value

    return new PricePerPackage(packageName, unitPrice, offset, discountPrice)
  }

  if (node.nodes.length === 0 && typeof node.attributes.value === 'number') {
    return new FixedPrice(node.attributes.value)
  }

  return new RawPriceExpression(expression)
}

export const parsePriceAST = (ast, expression) => parsePriceNode(ast.nodes, expression)
