import parsePricingRule, { parseAST } from '../pricing-rule-parser'

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

const ast = {
  "nodes":{
    "nodes":{
      "left":{
        "nodes":{
          "left":{
            "nodes":{
              "left":{
                "nodes":[

                ],
                "attributes":{
                  "name":"distance"
                }
              },
              "right":{
                "nodes":[

                ],
                "attributes":{
                  "value":0
                }
              }
            },
            "attributes":{
              "operator":">"
            }
          },
          "right":{
            "nodes":{
              "arguments":{
                "nodes":[
                  {
                    "nodes":{
                      "node":{
                        "nodes":[

                        ],
                        "attributes":{
                          "name":"dropoff"
                        }
                      },
                      "attribute":{
                        "nodes":[

                        ],
                        "attributes":{
                          "value":"address"
                        }
                      },
                      "arguments":{
                        "nodes":[

                        ],
                        "attributes":[

                        ]
                      }
                    },
                    "attributes":{
                      "type":1
                    }
                  },
                  {
                    "nodes":[

                    ],
                    "attributes":{
                      "value":"foo and bar"
                    }
                  }
                ],
                "attributes":[

                ]
              }
            },
            "attributes":{
              "name":"in_zone"
            }
          }
        },
        "attributes":{
          "operator":"and"
        }
      },
      "right":{
        "nodes":{
          "left":{
            "nodes":[

            ],
            "attributes":{
              "name":"weight"
            }
          },
          "right":{
            "nodes":[

            ],
            "attributes":{
              "value":0
            }
          }
        },
        "attributes":{
          "operator":">"
        }
      }
    },
    "attributes":{
      "operator":"and"
    }
  }
}

const astWithPackages = {"nodes":{"nodes":{"left":{"nodes":{"left":{"nodes":[],"attributes":{"name":"distance"}},"right":{"nodes":{"left":{"nodes":[],"attributes":{"value":12000}},"right":{"nodes":[],"attributes":{"value":16000}}},"attributes":{"operator":".."}}},"attributes":{"operator":"in"}},"right":{"nodes":{"node":{"nodes":[],"attributes":{"name":"packages"}},"attribute":{"nodes":[],"attributes":{"value":"containsAtLeastOne"}},"arguments":{"nodes":[{"nodes":[],"attributes":{"value":0}},{"nodes":[],"attributes":{"value":"Grand"}}],"attributes":[]}},"attributes":{"type":2}}},"attributes":{"operator":"and"}}}

const astWithDiffHours = {"nodes":{"nodes":{"left":{"nodes":{"left":{"nodes":[],"attributes":{"name":"distance"}},"right":{"nodes":{"left":{"nodes":[],"attributes":{"value":16000}},"right":{"nodes":[],"attributes":{"value":17000}}},"attributes":{"operator":".."}}},"attributes":{"operator":"in"}},"right":{"nodes":{"left":{"nodes":{"arguments":{"nodes":[{"nodes":[],"attributes":{"name":"pickup"}}],"attributes":[]}},"attributes":{"name":"diff_hours"}},"right":{"nodes":[],"attributes":{"value":3}}},"attributes":{"operator":"<"}}},"attributes":{"operator":"and"}}}

describe('Pricing rule parser (AST)', function() {

  it('should parse AST', function() {

    const result = parseAST(ast)

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

    const result = parseAST(astWithPackages)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 12000, 16000 ] },
        { left: 'packages', operator: 'containsAtLeastOne', right: 'Grand' }
      ]
    )

  })

  it('should parse AST with diff hours', function() {

    const result = parseAST(astWithDiffHours)

    expect(result).toEqual(
      [
        { left: 'distance', operator: 'in', right: [ 16000, 17000 ] },
        { left: 'diff_hours(pickup)', operator: '<', right: 3 }
      ]
    )

  })
})
