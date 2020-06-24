import _ from 'lodash'

export const calculate = (base, amount, isIncludedInPrice) => {

  if (isIncludedInPrice) {
    return Math.round(base - (base / (1 + amount)))
  }

  return Math.round(base * amount)
}

export const totalTaxExcluded = (item) => {

  if (Object.prototype.hasOwnProperty.call(item.adjustments, 'tax')) {
    const taxTotal = _.sumBy(item.adjustments.tax, 'amount')

    return item.total - taxTotal
  }

  return item.total
}
