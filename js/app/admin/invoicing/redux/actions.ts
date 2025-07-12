import { baseQueryWithReauth } from '../../../api/baseQuery'

export function prepareParams({
  store,
  dateRange,
  state,
  onlyNotInvoiced,
}: {
  store?: string[]
  dateRange: string[]
  state?: string[]
  onlyNotInvoiced: boolean
}): string[] {
  let params = []

  if (store && store.length > 0) {
    params.push(...store.map(storeId => `store[]=${storeId}`))
  }

  if (state && state.length > 0) {
    params.push(...state.map(state => `state[]=${state}`))
  }

  params.push(`date[after]=${dateRange[0]}`)
  params.push(`date[before]=${dateRange[1]}`)

  if (onlyNotInvoiced) {
    params.push('exists[exports]=false')
  }

  return params
}

function downloadFile({ requestUrl, filename }) {
  return async (dispatch, getState) => {
    const result = await baseQueryWithReauth(
      {
        url: requestUrl,
        headers: {
          Accept: 'text/csv',
        },
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

    const requestId = result.meta.response.headers.get('X-Request-ID')

    const blob = new Blob([result.data], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.download = `${filename}_${requestId.substring(0, 7)}.csv`
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
