import { baseQueryWithReauth } from '../../../api/baseQuery'

export function prepareParams({ store, dateRange, state }) {
  let params = []

  params.push(...store.map(storeId => `store[]=${storeId}`))

  if (state && state.length > 0) {
    params.push(...state.map(state => `state[]=${state}`))
  }

  params.push(`date[after]=${dateRange[0]}`)
  params.push(`date[before]=${dateRange[1]}`)

  return params
}

function downloadFile({ requestUrl, filename }) {
  return async (dispatch, getState) => {
    const result = await baseQueryWithReauth(
      {
        url: requestUrl,
        responseHandler: 'text',
      },
      {
        dispatch,
        getState,
      },
    )

    if (result.error) {
      console.warn('error', result.error)
      return
    }

    const blob = new Blob([result.data], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.download = filename
    link.href = url
    link.click()

    URL.revokeObjectURL(url)
  }
}


export function downloadStandardFile({ params, filename }) {
  return downloadFile({
    requestUrl: `api/invoice_line_items/export?${params.join('&')}`,
    filename,
  })
}

export function downloadOdooFile({ params, filename }) {
  return downloadFile({
    requestUrl: `api/invoice_line_items/export/odoo?${params.join('&')}`,
    filename,
  })
}
