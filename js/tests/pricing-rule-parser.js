import parsePricingRule from '../app/delivery/pricing-rule-parser'
import chai from 'chai'

const expect = chai.expect

describe('Pricing rule parser', function() {

  it('should parse in_zone', function() {
    const expression = 'in_zone(pickup.address, "bordeaux_centre_ville")'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'pickup.address',
      operator: 'in_zone',
      right: 'bordeaux_centre_ville'
    }])
  })

  it('should parse out_zone', function() {
    const expression = 'out_zone(pickup.address, "bordeaux_centre_ville")'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'pickup.address',
      operator: 'out_zone',
      right: 'bordeaux_centre_ville'
    }])
  })

  it('should parse in_zone with zone containing "and"', function() {
    const expression = 'in_zone(pickup.address, "commander")'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'pickup.address',
      operator: 'in_zone',
      right: 'commander'
    }])
  })

  it('should parse vehicle', function() {
    const expression = 'vehicle == "cargo_bike"'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'vehicle',
      operator: '==',
      right: 'cargo_bike'
    }])
  })

  it('should parse vehicle with extra space', function() {
    const expression = 'vehicle  == "cargo_bike"'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'vehicle',
      operator: '==',
      right: 'cargo_bike'
    }])
  })

  it('should parse distance with bounds', function() {
    const expression = 'distance in 0..3000'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'distance',
      operator: 'in',
      right: [ 0, 3000 ]
    }])
  })

  it('should parse distance with comparison', function() {
    const expression = 'distance < 3000'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'distance',
      operator: '<',
      right: 3000
    }])
  })

  it('should parse weight with bounds', function() {
    const expression = 'weight in 4000..6000'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'weight',
      operator: 'in',
      right: [ 4000, 6000 ]
    }])
  })

  it('should parse weight with comparison', function() {
    const expression = 'weight > 4000'
    const result = parsePricingRule(expression)
    expect(result).to.deep.equal([{
      left: 'weight',
      operator: '>',
      right: 4000
    }])
  })

  it('should return empty array', function() {
    const result = parsePricingRule('')
    expect(result).to.deep.equal([])
  })

})
