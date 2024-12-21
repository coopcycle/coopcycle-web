import { baseQueryWithReauth } from '../../../api/baseQuery'

function prepareArgs(args) {
  let params = args.store.map(storeId => `store[]=${storeId}`).join('&')

  if (args.state && args.state.length > 0) {
    params += '&' + args.state.map(state => `state[]=${state}`).join('&')
  }

  return {
    url: `api/invoice_line_items/export?${params}`,
    params: {
      'date[after]': args.dateRange[0],
      'date[before]': args.dateRange[1],
    },
    responseHandler: 'text',
  }
}

export function downloadFile(args) {
  return async (dispatch, getState) => {
    const result = await baseQueryWithReauth(prepareArgs(args), {
      dispatch,
      getState,
    })

    if (result.error) {
      console.warn('error', result.error)
      return
    }

    const blob = new Blob([result.data], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.download = `orders_${args.dateRange[0]}_${args.dateRange[1]}.csv`
    link.href = url
    link.click()

    URL.revokeObjectURL(url)
  }
}
