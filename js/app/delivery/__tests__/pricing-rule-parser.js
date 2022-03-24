import parsePricingRule, { parseAST, parsePriceAST, FixedPrice, PriceRange, PricePerPackage, RawPriceExpression } from '../pricing-rule-parser'
import withZone from './with-zone.json'

import withPackages from './with-packages.json'
import withDiffHours from './with-diff-hours.json'
import withDropoffDoorstep from './with-dropoff-doorstep.json'
import withOrderItemsTotal from './with-order-items-total.json'
import withOrderItemsTotalRange from './with-order-items-total-range.json'
import withTotalVolumeUnits from './with-packages-total-volume-units.json'
import withTotalVolumeUnitsRange from './with-packages-total-volume-units-range.json'
import withTimeRangeLength from './with-time-range-length.json'
import withDropoffTimeRangeLength from './with-time-range-length-dropoff.json'
import withDropoffTimeRangeLengthWithRange from './with-time-range-length-dropoff-in.json'

import fixedPrice from './fixed-price.json'
import priceRange from './price-range.json'
import rawPriceFormula from './raw-price-formula.json'
import pricePerPackageFormula from './price-per-package.json'
import pricePerPackageFunctionFormula from './price-per-package-function.json'
import priceRangeWithTotalVolumeUnits from './price-range-with-total-volume-units.json'

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

  it.skip('should parse in_zone with zone containing "and" with spaces', function() {
    const expression = 'in_zone(pickup.address, "plug and play")'
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

describe('Pricing rule parser (AST)', function() {

  it('should parse AST', function() {

    const result = parseAST(withZone)

    expect(result).toEqual(
      [
        { left: 'distance', operator: '>', right: 0 },
        {
          left: 'dropoff.address',
          operator: 'in_zone',
          right: 'foo and bar'
        },
        { left: 'weight', operator: '>', right: 0 }
      ]
    )

  })

  it('should parse AST with packages', function() {

    const result = parseAST(withPackages)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 12000, 16000 ] },
        { left: 'packages', operator: 'containsAtLeastOne', right: 'Grand' }
      ]
    )

  })

  it('should parse AST with diff hours', function() {

    const result = parseAST(withDiffHours)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 16000, 17000 ] },
        { left: 'diff_hours(pickup)', operator: '<', right: 3 }
      ]
    )

  })

  it('should parse AST with dropoff doorstep', function() {

    const result = parseAST(withDropoffDoorstep)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 1000, 8000 ] },
        { left: 'pickup.address', operator: 'in_zone', right: 'Test' },
        { left: 'dropoff.doorstep', operator: '==', right: true }
      ]
    )

  })

  it('should parse AST with order items total', function() {

    const result = parseAST(withOrderItemsTotal)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 1000, 8000 ] },
        { left: 'order.itemsTotal', operator: '>', right: 10 }
      ]
    )
  })

  it('should parse AST with order items total (range)', function() {

    const result = parseAST(withOrderItemsTotalRange)

    expect(result).toEqual(
      [
        { left: 'order.itemsTotal', operator: 'in', right: [ 2300, 2599 ] }
      ]
    )
  })

  it('should parse AST with total volume units', function() {

    const result = parseAST(withTotalVolumeUnits)

    expect(result).toEqual(
      [
        { left: 'packages.totalVolumeUnits()', operator: '>', right: 3 }
      ]
    )
  })

  it('should parse AST with total volume units (range)', function() {

    const result = parseAST(withTotalVolumeUnitsRange)

    expect(result).toEqual(
      [
        { left: 'packages.totalVolumeUnits()', operator: 'in', right: [1, 5] }
      ]
    )
  })

  it('should parse AST with pickup time range length', function() {

    const result = parseAST(withTimeRangeLength)

    expect(result).toEqual(
      [
        { left: 'time_range_length(pickup, \'hours\')', operator: '<', right: 2 }
      ]
    )
  })

  it('should parse AST with dropoff time range length', function() {

    const result = parseAST(withDropoffTimeRangeLength)

    expect(result).toEqual(
      [
        { left: 'time_range_length(dropoff, \'hours\')', operator: '<', right: 2 }
      ]
    )
  })

  it('should parse AST with dropoff time range length with range', function() {

    const result = parseAST(withDropoffTimeRangeLengthWithRange)

    expect(result).toEqual(
      [
        { left: 'time_range_length(dropoff, \'hours\')', operator: 'in', right: [ 2, 8 ] }
      ]
    )
  })
})

describe('Pricing rule price parser (AST)', function() {

  it('should parse fixed price', function() {
    const result = parsePriceAST(fixedPrice, '1053')
    expect(result).toBeInstanceOf(FixedPrice);
    expect(result.value).toBe(1053);
  })

  it('should parse price range', function() {
    const result = parsePriceAST(priceRange, 'price_range(distance, 450, 2000, 2500)')
    expect(result).toBeInstanceOf(PriceRange);
    expect(result.attribute).toBe('distance');
    expect(result.price).toBe(450);
    expect(result.step).toBe(2000);
    expect(result.threshold).toBe(2500);
  })

  it('should parse price range with total volume units', function() {
    const result = parsePriceAST(priceRangeWithTotalVolumeUnits, 'price_range(packages.totalVolumeUnits(), 100, 1, 0)')
    expect(result).toBeInstanceOf(PriceRange);
    expect(result.attribute).toBe('packages.totalVolumeUnits()');
    expect(result.price).toBe(100);
    expect(result.step).toBe(1);
    expect(result.threshold).toBe(0);
  })

  it('should parse price per package', function() {
    const result = parsePriceAST(pricePerPackageFormula, 'packages.quantity("XXL") * 240')
    expect(result).toBeInstanceOf(PricePerPackage);
    expect(result.packageName).toBe('XXL');
    expect(result.unitPrice).toBe(240);
  })

  it('should parse price per package function', function() {
    const result = parsePriceAST(pricePerPackageFunctionFormula, 'price_per_package(packages, "XXL", 1240, 3, 210)')
    expect(result).toBeInstanceOf(PricePerPackage);
    expect(result.packageName).toBe('XXL');
    expect(result.unitPrice).toBe(1240);
    expect(result.offset).toBe(3);
    expect(result.discountPrice).toBe(210);
  })

  it('should parse raw formula', function() {
    const result = parsePriceAST(rawPriceFormula, '1800 + (ceil((distance - 8000) / 1000) * 360)')
    expect(result).toBeInstanceOf(RawPriceExpression);
    expect(result.expression).toBe('1800 + (ceil((distance - 8000) / 1000) * 360)');
  })
})
