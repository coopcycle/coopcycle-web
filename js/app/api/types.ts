// API Types for RTK Query endpoints

// Common Hydra JSON-LD response structure
export interface HydraCollection<T> {
  'hydra:member': T[]
  'hydra:totalItems': number
  'hydra:view'?: {
    '@id': string
    '@type': string
    'hydra:first'?: string
    'hydra:last'?: string
    'hydra:previous'?: string
    'hydra:next'?: string
  }
}

export interface InvoiceLineItemGroupedByOrganization {
  storeId: number
  organizationLegalName: string
  ordersCount: number
  subTotal: number
  tax: number
  total: number
}

export interface GetInvoiceLineItemsGroupedByOrganizationArgs {
  params: string[]
  page: number
  pageSize: number
}

export interface InvoiceLineItem {
  '@id': string
  '@type': string
  storeId: number
  orderId: string
  orderNumber: string
  date: string
  description: string
  subTotal: number
  tax: number
  total: number
  exports: Array<{
    requestId: string
    createdAt: string
  }>
}

export interface GetInvoiceLineItemsArgs {
  params: string[]
  page: number
  pageSize: number
}
