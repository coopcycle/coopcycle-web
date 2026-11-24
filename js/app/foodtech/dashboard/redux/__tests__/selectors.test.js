import { selectIsRestaurantClosedUntilTomorrow } from '../selectors'

describe('selectIsRestaurantClosedUntilTomorrow', () => {

  beforeEach(() => {
    jest.useFakeTimers('modern')
    jest.setSystemTime(new Date(2020, 9, 2, 11, 0, 0))
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  const closingRule = (validFrom, closes, validThrough, opens) => ({
    '@type': 'OpeningHoursSpecification',
    opens,
    closes,
    validFrom,
    validThrough,
  })

  it('returns false without restaurant', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({ restaurant: null })).toBe(false)
  })

  it('returns false without closing rules', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: { specialOpeningHoursSpecification: [] },
    })).toBe(false)
  })

  it('returns true when the restaurant was closed for today', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: {
        specialOpeningHoursSpecification: [
          closingRule('2020-10-02', '00:00', '2020-10-02', '23:59'),
        ],
      },
    })).toBe(true)
  })

  it('returns true when the closing rules are serialized as an object', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: {
        specialOpeningHoursSpecification: {
          2: closingRule('2020-10-02', '00:00', '2020-10-02', '23:59'),
        },
      },
    })).toBe(true)
  })

  it('returns true when a closing rule covers the rest of the day', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: {
        specialOpeningHoursSpecification: [
          closingRule('2020-09-28', '10:00', '2020-10-05', '12:00'),
        ],
      },
    })).toBe(true)
  })

  it('returns false when a closing rule ends before the end of the day', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: {
        specialOpeningHoursSpecification: [
          closingRule('2020-10-02', '00:00', '2020-10-02', '18:00'),
        ],
      },
    })).toBe(false)
  })

  it('returns false when a closing rule starts later', () => {
    expect(selectIsRestaurantClosedUntilTomorrow({
      restaurant: {
        specialOpeningHoursSpecification: [
          closingRule('2020-10-02', '14:00', '2020-10-02', '23:59'),
          closingRule('2020-10-03', '00:00', '2020-10-03', '23:59'),
        ],
      },
    })).toBe(false)
  })

})
