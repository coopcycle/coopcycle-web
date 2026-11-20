import reducers from '../reducers'
import { ORDER_CREATED, CLOSE_RESTAURANT_REQUEST_SUCCESS } from '../actions'

describe('reducers', () => {

  it('should handle created order', () => {

    expect(
      reducers({
        date: '2020-05-07',
        orders: [],
      }, {
        type: ORDER_CREATED,
        payload: {
          shippingTimeRange: [
            '2020-05-07T10:30:00+02:00',
            '2020-05-07T14:00:00+02:00',
          ],
        }
      })
    ).toMatchObject({
      date: '2020-05-07',
      orders: [
        {
          shippingTimeRange: [
            '2020-05-07T10:30:00+02:00',
            '2020-05-07T14:00:00+02:00',
          ],
        }
      ]
    })

    expect(
      reducers({
        date: '2020-05-07',
        orders: [],
      }, {
        type: ORDER_CREATED,
        payload: {
          shippingTimeRange: [
            '2020-05-08T10:30:00+02:00',
            '2020-05-08T14:00:00+02:00',
          ],
        }
      })
    ).toMatchObject({
      date: '2020-05-07',
      orders: []
    })

  })

  it('should handle closed restaurant', () => {

    const specialOpeningHoursSpecification = [
      {
        '@id': '/api/opening_hours_specifications/1',
        '@type': 'OpeningHoursSpecification',
        id: 1,
        opens: '23:59',
        closes: '00:00',
        validFrom: '2020-10-02',
        validThrough: '2020-10-02',
      }
    ]

    expect(
      reducers({
        restaurant: {
          '@id': '/api/restaurants/1',
          name: 'Nodaiwa',
          state: 'rush',
          specialOpeningHoursSpecification: [],
        },
      }, {
        type: CLOSE_RESTAURANT_REQUEST_SUCCESS,
        payload: {
          '@id': '/api/restaurants/1',
          name: 'Nodaiwa',
          state: 'normal',
          specialOpeningHoursSpecification,
        }
      })
    ).toEqual({
      restaurant: {
        '@id': '/api/restaurants/1',
        name: 'Nodaiwa',
        state: 'rush',
        specialOpeningHoursSpecification,
      },
    })

  })

})
