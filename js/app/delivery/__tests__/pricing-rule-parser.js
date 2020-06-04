import parsePricingRule from '../pricing-rule-parser'

describe('Pricing rule parser', function() {

  it('should parse in_zone', function() {
    const expression = 'in_zone(pickup.address, "bordeaux_centre_ville")'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'pickup.address',
      operator: 'in_zone',
      right: 'bordeaux_centre_ville'
    }])
  })

  it('should parse out_zone', function() {
    const expression = 'out_zone(pickup.address, "bordeaux_centre_ville")'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'pickup.address',
      operator: 'out_zone',
      right: 'bordeaux_centre_ville'
    }])
  })

  it('should parse in_zone with zone containing "and"', function() {
    const expression = 'in_zone(pickup.address, "commander")'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'pickup.address',
      operator: 'in_zone',
      right: 'commander'
    }])
  })

  it('should parse vehicle', function() {
    const expression = 'vehicle == "cargo_bike"'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'vehicle',
      operator: '==',
      right: 'cargo_bike'
    }])
  })

  it('should parse vehicle with extra space', function() {
    const expression = 'vehicle  == "cargo_bike"'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'vehicle',
      operator: '==',
      right: 'cargo_bike'
    }])
  })

  it('should parse distance with bounds', function() {
    const expression = 'distance in 0..3000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'distance',
      operator: 'in',
      right: [ 0, 3000 ]
    }])
  })

  it('should parse distance with comparison', function() {
    const expression = 'distance < 3000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'distance',
      operator: '<',
      right: 3000
    }])
  })

  it('should parse distance with comparison to zero', function() {
    const expression = 'distance > 0'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'distance',
      operator: '>',
      right: 0
    }])
  })

  it('should parse weight with bounds', function() {
    const expression = 'weight in 4000..6000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'weight',
      operator: 'in',
      right: [ 4000, 6000 ]
    }])
  })

  it('should parse weight with comparison', function() {
    const expression = 'weight > 4000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'weight',
      operator: '>',
      right: 4000
    }])
  })

  it('should parse doorstep dropoff', function() {
    const expression = 'dropoff.doorstep == true'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'dropoff.doorstep',
      operator: '==',
      right: 'true'
    }])
  })

  it('should parse diff_days with equality', function() {
    const expression = 'diff_days(pickup) == 1'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'diff_days(pickup)',
      operator: '==',
      right: 1
    }])
  })

  it('should parse diff_hours with comparison', function() {
    const expression = 'diff_hours(pickup) > 2'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'diff_hours(pickup)',
      operator: '>',
      right: 2
    }])
  })

  it('should parse packages', function() {
    const expression = 'packages.containsAtLeastOne("XL")'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'packages',
      operator: 'containsAtLeastOne',
      right: 'XL'
    }])
  })

  it('should parse order.itemsTotal with comparator', function() {
    const expression = 'order.itemsTotal > 3000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'order.itemsTotal',
      operator: '>',
      right: 3000
    }])
  })

  it('should parse order.itemsTotal with range', function() {
    const expression = 'order.itemsTotal in 3000..5000'
    const result = parsePricingRule(expression)
    expect(result).toEqual([{
      left: 'order.itemsTotal',
      operator: 'in',
      right: [ 3000, 5000 ]
    }])
  })

  it('should return empty array', function() {
    const result = parsePricingRule('')
    expect(result).toEqual([])
  })

})
