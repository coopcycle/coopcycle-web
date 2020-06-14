export const calculate = (base, amount, isIncludedInPrice) => {

  if (isIncludedInPrice) {
    return Math.round(base - (base / (1 + amount)))
  }

  return Math.round(base * amount)
}
