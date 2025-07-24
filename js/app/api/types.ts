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

// Base JSON-LD entity interface
export interface JsonLdEntity {
  '@id': string
  '@type': string
}

export interface GeoCoordinates {
  latitude: number
  longitude: number
}

export interface TsRange {
  lower: string
  upper: string
}

export interface TaxRate {
  id: string
  code: string
  amount: number
  name: string
  category: string
  alternatives: TaxRate[]
}

export interface Tag extends JsonLdEntity {
  id: number
  name: string
  slug: string
  color: string
}

export interface Zone extends JsonLdEntity {
  id: number
  name: string
  polygon: string // GeoJSON string
}

export interface Package extends JsonLdEntity {
  id: number
  name: string
  volumeUnits: number
  packageSet?: PackageSet
}

export interface PackageSet {
  id: number
  name: string
}

export interface Address extends JsonLdEntity {
  id: number
  streetAddress: string
  addressLocality: string
  addressCountry: string
  addressRegion?: string
  postalCode: string
  geo: GeoCoordinates
  name?: string
  description?: string
  contactName?: string
  telephone?: string
  company?: string
}

export interface TimeSlot extends JsonLdEntity {
  id: number
  name: string
  interval: string
  workingDaysOnly: boolean
  priorNotice?: string
  openingHours?: string[]
  choices?: any[] // deprecated field
}

export interface TimeSlotChoice {
  '@id': string
  '@type': string
  startTime: string
  endTime: string
}

export interface Store extends JsonLdEntity {
  id: number
  name: string
  enabled: boolean
  address: Address
  prefillPickupAddress: boolean
  timeSlot?: TimeSlot
  weightRequired: boolean
  packagesRequired: boolean
  multiDropEnabled: boolean
  multiPickupEnabled: boolean
  timeSlots: TimeSlot[]
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

export interface Customer {
  username: string
  email: string
  fullName: string
  telephone?: string
  phoneNumber?: string
}

export interface OrderItem {
  id: number
  quantity: number
  total: number
  unitPrice: number
  adjustments: Adjustment[]
  variant?: any
}

export interface Adjustment {
  label: string
  amount: number
  type: string
}

export interface Order extends JsonLdEntity {
  id: number
  number: string
  state: string
  total: number
  itemsTotal: number
  taxTotal: number
  customer: Customer
  vendor?: any
  restaurant?: any
  shippingAddress: Address
  shippingTimeRange: TsRange
  items: OrderItem[]
  notes?: string
  createdAt: string
  takeaway: boolean
  fulfillmentMethod?: string
  paymentMethod?: string
  hasReceipt?: boolean
  shippedAt?: string
  events?: any[]
  timeline?: any[]
  preparationExpectedAt?: string
  pickupExpectedAt?: string
  reusablePackagingEnabled?: boolean
  reusablePackagingPledgeReturn?: number
  reusablePackagingQuantity?: number
  preparationTime?: string
  shippingTime?: string
  hasEdenredCredentials?: boolean
}

export interface TaskPackage {
  short_code: string
  name: string
  type: string
  volume_per_package: number
  quantity: string | number
  labels: string[]
}

export interface TaskGroup {
  id: number
  name: string
}

export interface User {
  username: string
  email: string
  telephone?: string
  roles: string[]
  givenName?: string
  familyName?: string
}

export interface FailureReason {
  code: string
  description: string
}

export interface Task extends JsonLdEntity {
  id: number
  type: 'PICKUP' | 'DROPOFF'
  status: string
  address: Address
  comments?: string
  createdAt: string
  updatedAt?: string
  group?: TaskGroup
  assignedTo?: User
  doorstep: boolean
  ref?: string
  recurrenceRule?: any
  metadata: Record<string, any>
  weight?: number
  incidents?: any[]
  emittedCo2: number
  traveledDistanceMeter: number
  packages?: TaskPackage[]
}

export interface Delivery extends JsonLdEntity {
  id: number
  pickup?: Task
  dropoff?: Task
  tasks: Task[]
  createdAt: string
  updatedAt?: string
  store?: Store
  order?: Order
  vehicle?: string
  packages?: TaskPackage[]
}

export interface RecurrenceRule extends JsonLdEntity {
  id: number
  rule: string
  template: any
  store?: Store
  generateOrders?: boolean
}

export interface PricingRule {
  id: number
  expression: string
  price: number
  position: number
  ruleSet?: PricingRuleSet
}

export interface PricingRuleSet extends JsonLdEntity {
  id: number
  name: string
  strategy: string
  options: Record<string, any>
  rules: PricingRule[]
}

export interface OptimizationSuggestion {
  gain: Record<string, any>
  order: any[]
}

export interface OptimizationSuggestions {
  suggestions: OptimizationSuggestion[]
}

export interface CalculationItem {
  ruleSet: PricingRuleSet
  strategy: string
  items: any[]
}

export interface CalculationOutput {
  ruleSet: PricingRuleSet
  strategy: string
  items: CalculationItem[]
}

// API Request/Response types for mutations
export interface UpdateOrderRequest {
  nodeId: string
  [key: string]: any
}

export interface PatchAddressRequest {
  nodeId: string
  [key: string]: any
}

export interface PostStoreAddressRequest {
  storeNodeId: string
  [key: string]: any
}

export interface CalculatePriceRequest {
  [key: string]: any
}

export interface SuggestOptimizationsRequest {
  [key: string]: any
}

export interface PostDeliveryRequest {
  [key: string]: any
}

export interface PutDeliveryRequest {
  nodeId: string
  [key: string]: any
}

export interface PutRecurrenceRuleRequest {
  nodeId: string
  [key: string]: any
}

export interface CreatePricingRuleSetRequest {
  name: string
  strategy?: string
  options?: Record<string, any>
  rules?: PricingRule[]
}

export interface UpdatePricingRuleSetRequest {
  id: number
  name?: string
  strategy?: string
  options?: Record<string, any>
  rules?: PricingRule[]
}

// For recurrenceRulesGenerateOrders, the parameter is a moment.js date object
export type RecurrenceRulesGenerateOrdersRequest = {
  format: (format: string) => string // Moment.js date object with format method
}

// Order timing and validation response types
export interface OrderTiming {
  [key: string]: any // The exact structure depends on the timing calculation
}

export interface OrderValidation {
  [key: string]: any // The exact structure depends on the validation result
}
