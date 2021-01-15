import reducers from '../reducers'
import { ORDER_CREATED } from '../actions'

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

})
