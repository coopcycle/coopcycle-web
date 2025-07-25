// API Types for RTK Query endpoints

export type NodeId = string

// Base JSON-LD entity interface
export interface JsonLdEntity {
  '@id': NodeId
  '@type': string
}

// Common Hydra JSON-LD response structure
export interface HydraCollection<T> {
  'hydra:member': T[]
  'hydra:totalItems': number
  'hydra:view'?: {
    '@id': NodeId
    '@type': string
    'hydra:first'?: string
    'hydra:last'?: string
    'hydra:previous'?: string
    'hydra:next'?: string
  }
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
}

export interface TimeSlotChoice {
  // "2025-07-24T07:00:00Z/2025-07-25T06:59:00Z"
  value: string
  label: string
}

export interface TimeSlotChoices {
  choices: TimeSlotChoice[]
}

export interface Store extends JsonLdEntity {
  id: number
  name: string
  enabled: boolean
  address: Address
  timeSlot?: NodeId
  timeSlots: NodeId[]
  pricingRuleSet?: NodeId
  prefillPickupAddress: boolean
  weightRequired: boolean
  packagesRequired: boolean
  multiDropEnabled: boolean
  multiPickupEnabled: boolean
}

export interface ProductOptionValue extends JsonLdEntity {
  value: string
  price: number
}

export interface ProductVariant extends JsonLdEntity {
  id: number
  name: string
  code: string
  price: number
  optionValues: ProductOptionValue[]
}

export interface LocalBusiness extends JsonLdEntity {
  id: number
  name: string
  enabled: boolean
  description?: string
  address?: Address
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
  variant?: ProductVariant
}

export interface Adjustment {
  label: string
  amount: number
  type: string
}

export interface OrderEvent {
  name: string
  createdAt: string
  // data?: Record<string, any>
}

export interface OrderTimeline {
  preparationExpectedAt?: string
  pickupExpectedAt?: string
  dropoffExpectedAt?: string
  preparationTime?: string
  shippingTime?: string
}

export interface Order extends JsonLdEntity {
  id: number
  number: string
  state: string
  total: number
  itemsTotal: number
  taxTotal: number
  customer: Customer
  vendor?: LocalBusiness
  restaurant?: LocalBusiness
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
  events?: OrderEvent[]
  timeline?: OrderTimeline
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

export interface IncidentEvent {
  id: number
  type: string
  message?: string
  // metadata?: Record<string, any>
  createdBy?: User
  createdAt: string
}

export interface Incident {
  id: number
  title?: string
  status: string
  priority: number
  failureReasonCode?: string
  description?: string
  events: IncidentEvent[]
  createdBy?: User
  // metadata: Record<string, any>
  createdAt: string
  updatedAt?: string
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
  recurrenceRule?: RecurrenceRule
  // metadata: Record<string, any>
  weight?: number
  incidents?: Incident[]
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
}

// Delivery Template for RecurrenceRule
export interface DeliveryTemplate {
  '@type': string
  'hydra:member'?: TaskTemplate[]
  // For single task templates
  after?: string
  before?: string
  address?: Partial<Address>
  type?: 'PICKUP' | 'DROPOFF'
  comments?: string
  packages?: TaskPackage[]
  weight?: number
}

export interface TaskTemplate {
  '@type': string
  after: string
  before: string
  address: Partial<Address>
  type: 'PICKUP' | 'DROPOFF'
  comments?: string
  packages?: TaskPackage[]
  weight?: number
}

export interface RecurrenceRule extends JsonLdEntity {
  id: number
  rule: string
  template: DeliveryTemplate
  store?: Store
  generateOrders?: boolean
}

export type PricingRuleTarget = 'DELIVERY' | 'TASK' | 'LEGACY_TARGET_DYNAMIC'

export interface PricingRule extends JsonLdEntity {
  id: number
  target: PricingRuleTarget
  position: number
  name?: string
  expression: string
  expressionAst: object
  price: string
  priceAst: object
}

export interface PricingRuleSet extends JsonLdEntity {
  id: number
  name: string
  strategy: string
  // options: Record<string, any>
  rules: PricingRule[]
}

export interface OptimizationGain {
  distance?: number
  duration?: number
  co2?: number
  cost?: number
}

export interface OptimizationSuggestion {
  gain: OptimizationGain
  order: Task[]
}

export interface OptimizationSuggestions {
  suggestions: OptimizationSuggestion[]
}

export interface CalculationItemDetail {
  rule: PricingRule
  price: number
  matched: boolean
}

export interface CalculationItem {
  ruleSet: PricingRuleSet
  strategy: string
  items: CalculationItemDetail[]
}

export interface CalculationOutput {
  ruleSet: PricingRuleSet
  strategy: string
  items: CalculationItem[]
  order: Order
}

export interface UpdateOrderRequest {
  nodeId: string
  state?: string
  notes?: string
  shippingTimeRange?: TsRange
  fulfillmentMethod?: string
  paymentMethod?: string
  reusablePackagingEnabled?: boolean
  reusablePackagingPledgeReturn?: number
  reusablePackagingQuantity?: number
}

export interface PatchAddressRequest {
  nodeId: string
  streetAddress?: string
  addressLocality?: string
  addressCountry?: string
  addressRegion?: string
  postalCode?: string
  name?: string
  description?: string
  contactName?: string
  telephone?: string
  company?: string
}

export interface PostStoreAddressRequest {
  storeNodeId: string
  streetAddress: string
  addressLocality: string
  addressCountry: string
  addressRegion?: string
  postalCode: string
  name?: string
  description?: string
  contactName?: string
  telephone?: string
  company?: string
}

export interface CalculatePriceRequest {
  delivery: Partial<Delivery>
  pricing_rule_set?: string
}

export interface SuggestOptimizationsRequest {
  tasks: Task[]
  vehicle?: string
}

export interface OrderPayload {
  arbitraryPrice: {
    variantName: string
    variantPrice: number
  }
}

export interface AddressPayload {
  '@id'?: string
  streetAddress: string
  name: string
  contactName: string
  telephone: string | null
  formattedTelephone?: string | null // Form-specific field for display
  geo?: {
    latitude: number
    longitude: number
  }
  description?: string
}

export interface TaskPayload {
  '@id'?: string
  id: number
  createdAt: string
  updatedAt?: string
  type: 'PICKUP' | 'DROPOFF'
  after: string
  before: string
  timeSlot: TimeSlot | null
  timeSlotUrl: string | null
  comments: string
  address: AddressPayload
  updateInStoreAddresses?: boolean
  saveInStoreAddresses?: boolean
  packages: Package[]
  weight: number
  tags: Tag[]
  doorstep?: boolean
}

export interface PostDeliveryRequest {
  store?: string
  tasks: TaskPayload[]
  order?: OrderPayload
  rrule?: string
}

export interface PutDeliveryRequest {
  nodeId: string
  tasks?: TaskPayload[]
  order?: OrderPayload
}

export interface PutRecurrenceRuleRequest {
  nodeId: string
  rule?: string
  template?: DeliveryTemplate
  store?: string
  generateOrders?: boolean
}

export interface CreatePricingRuleSetRequest {
  name: string
  strategy?: string
  // options?: Record<string, any>
  rules?: PricingRule[]
}

export interface UpdatePricingRuleSetRequest {
  id: number
  name?: string
  strategy?: string
  // options?: Record<string, any>
  rules?: PricingRule[]
}

// For recurrenceRulesGenerateOrders, the parameter is a moment.js date object
export type RecurrenceRulesGenerateOrdersRequest = {
  format: (format: string) => string // Moment.js date object with format method
}

export interface OrderTiming {
  preparation?: {
    expectedAt: string
    time: string
  }
  pickup?: {
    expectedAt: string
    time: string
  }
  dropoff?: {
    expectedAt: string
    time: string
  }
  shipping?: {
    time: string
  }
}

export interface OrderValidation {
  valid: boolean
  errors?: string[]
  warnings?: string[]
}

export interface RecurrenceRulesGenerateOrdersResponse {
  generated: number
  orders: Order[]
}
