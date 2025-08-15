// API Types for RTK Query endpoints

export type Uri = string;

// Base JSON-LD entity interface
export interface JsonLdEntity {
  '@id': Uri;
  '@type': string;
}

// Common Hydra JSON-LD response structure
export interface HydraCollection<T> {
  'hydra:member': T[];
  'hydra:totalItems': number;
  'hydra:view'?: {
    '@id': Uri;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:previous'?: string;
    'hydra:next'?: string;
  };
}

export type GeoCoordinates = {
  latitude: number;
  longitude: number;
};

export type TsRange = {
  lower: string;
  upper: string;
};

export type TaxRate = {
  id: string;
  code: string;
  amount: number;
  name: string;
  category: string;
  alternatives: TaxRate[];
};

export type Tag = JsonLdEntity & {
  id: number;
  name: string;
  slug: string;
  color: string;
};

export type Zone = JsonLdEntity & {
  id: number;
  name: string;
  polygon: string; // GeoJSON string
};

export type Package = JsonLdEntity & {
  id: number;
  name: string;
  volumeUnits: number;
  packageSet?: PackageSet;
};

export type PackageSet = {
  id: number;
  name: string;
};

export type Address = JsonLdEntity & {
  id: number;
  streetAddress: string;
  addressLocality: string;
  addressCountry: string;
  addressRegion?: string;
  postalCode: string;
  geo: GeoCoordinates;
  name?: string;
  description?: string;
  contactName?: string;
  telephone?: string;
  company?: string;
};

export type TimeSlot = JsonLdEntity & {
  id: number;
  name: string;
  interval: string;
  workingDaysOnly: boolean;
  priorNotice?: string;
  openingHours?: string[];
  choices?: TimeSlotChoice[];
};

export type StoreTimeSlot = JsonLdEntity & {
  id: number;
  name: string;
};

export type TimeSlotChoice = {
  // "2025-07-24T07:00:00Z/2025-07-25T06:59:00Z"
  value: string;
  label: string;
};

export type TimeSlotChoices = {
  choices: TimeSlotChoice[];
};

export type Store = JsonLdEntity & {
  id: number;
  name: string;
  enabled: boolean;
  address: Address;
  timeSlot?: Uri;
  timeSlots: Uri[];
  pricingRuleSet?: Uri;
  prefillPickupAddress: boolean;
  weightRequired: boolean;
  packagesRequired: boolean;
  multiDropEnabled: boolean;
  multiPickupEnabled: boolean;
};

export type ProductVariant = JsonLdEntity & {
  id: number;
  name: string;
  code: string;
  price: number;
};

export type LocalBusiness = JsonLdEntity & {
  id: number;
  name: string;
  enabled: boolean;
  description?: string;
  address?: Address;
};

export type InvoiceLineItemGroupedByOrganization = {
  storeId: number;
  organizationLegalName: string;
  ordersCount: number;
  subTotal: number;
  tax: number;
  total: number;
};

export type GetInvoiceLineItemsGroupedByOrganizationArgs = {
  params: string[];
  page: number;
  pageSize: number;
};

export type InvoiceLineItem = {
  '@id': string;
  '@type': string;
  storeId: number;
  orderId: string;
  orderNumber: string;
  date: string;
  description: string;
  subTotal: number;
  tax: number;
  total: number;
  exports: Array<{
    requestId: string;
    createdAt: string;
  }>;
};

export type GetInvoiceLineItemsArgs = {
  params: string[];
  page: number;
  pageSize: number;
};

export type Customer = {
  username: string;
  email: string;
  fullName: string;
  telephone?: string;
  phoneNumber?: string;
};

export type OrderItem = {
  id: number;
  quantity: number;
  total: number;
  unitPrice: number;
  adjustments: Record<string, Adjustment[]>;
  variant?: ProductVariant;
};

export type Adjustment = {
  label: string;
  amount: number;
  type: string;
};

export type OrderEvent = {
  name: string;
  createdAt: string;
  // data?: Record<string, any>
};

export type OrderTimeline = {
  preparationExpectedAt?: string;
  pickupExpectedAt?: string;
  dropoffExpectedAt?: string;
  preparationTime?: string;
  shippingTime?: string;
};

export type Order = JsonLdEntity & {
  id: number;
  number: string;
  state: string;
  total: number;
  itemsTotal: number;
  taxTotal: number;
  customer: Customer;
  vendor?: LocalBusiness;
  restaurant?: LocalBusiness;
  shippingAddress: Address;
  shippingTimeRange: TsRange;
  items: OrderItem[];
  notes?: string;
  createdAt: string;
  takeaway: boolean;
  fulfillmentMethod?: string;
  paymentMethod?: string;
  hasReceipt?: boolean;
  shippedAt?: string;
  events?: OrderEvent[];
  timeline?: OrderTimeline;
  preparationExpectedAt?: string;
  pickupExpectedAt?: string;
  reusablePackagingEnabled?: boolean;
  reusablePackagingPledgeReturn?: number;
  reusablePackagingQuantity?: number;
  preparationTime?: string;
  shippingTime?: string;
  hasEdenredCredentials?: boolean;
};

export type TaskPackage = {
  short_code: string;
  name: string;
  type: string;
  volume_per_package: number;
  quantity: string | number;
  labels: string[];
};

export type TaskGroup = {
  id: number;
  name: string;
};

export type User = {
  username: string;
  email: string;
  telephone?: string;
  roles: string[];
  givenName?: string;
  familyName?: string;
};

export type IncidentEvent = {
  id: number;
  type: string;
  message?: string;
  // metadata?: Record<string, any>
  createdBy?: User;
  createdAt: string;
};

export type Incident = {
  id: number;
  title?: string;
  status: string;
  priority: number;
  failureReasonCode?: string;
  description?: string;
  events: IncidentEvent[];
  createdBy?: User;
  // metadata: Record<string, any>
  createdAt: string;
  updatedAt?: string;
};

export type Task = JsonLdEntity & {
  id: number;
  type: 'PICKUP' | 'DROPOFF';
  status: string;
  address: Address;
  comments?: string;
  createdAt: string;
  updatedAt?: string;
  group?: TaskGroup;
  assignedTo?: User;
  doorstep: boolean;
  ref?: string;
  recurrenceRule?: RecurrenceRule;
  // metadata: Record<string, any>
  weight?: number;
  incidents?: Incident[];
  emittedCo2: number;
  traveledDistanceMeter: number;
  packages?: TaskPackage[];
};

export type Delivery = JsonLdEntity & {
  id: number;
  pickup?: Task;
  dropoff?: Task;
  tasks: Task[];
  createdAt: string;
  updatedAt?: string;
  store?: Store;
  order?: Order;
  trackingUrl?: string;
};

// Delivery Template for RecurrenceRule
export type DeliveryTemplate = {
  '@type': string;
  'hydra:member'?: TaskTemplate[];
  // For single task templates
  after?: string;
  before?: string;
  address?: Partial<Address>;
  type?: 'PICKUP' | 'DROPOFF';
  comments?: string;
  packages?: TaskPackage[];
  weight?: number;
};

export type TaskTemplate = {
  '@type': string;
  after: string;
  before: string;
  address: Partial<Address>;
  type: 'PICKUP' | 'DROPOFF';
  comments?: string;
  packages?: TaskPackage[];
  weight?: number;
};

export type RecurrenceRule = JsonLdEntity & {
  id: number;
  rule: string;
  template: DeliveryTemplate;
  store?: Store;
  generateOrders?: boolean;
};

export type PricingRuleTarget = 'DELIVERY' | 'TASK' | 'LEGACY_TARGET_DYNAMIC';

export type PricingRule = JsonLdEntity & {
  id: number;
  target: PricingRuleTarget;
  position: number;
  name?: string;
  expression: string;
  expressionAst: object;
  price: string;
  priceAst: object;
};

export type PricingRuleSet = JsonLdEntity & {
  id: number;
  name: string;
  strategy: string;
  // options: Record<string, any>
  rules: PricingRule[];
};

export type OptimizationGain = {
  distance?: number;
  duration?: number;
  co2?: number;
  cost?: number;
};

export type OptimizationSuggestion = {
  gain: OptimizationGain;
  order: Task[];
};

export type OptimizationSuggestions = {
  suggestions: OptimizationSuggestion[];
};

export type CalculationItemDetail = {
  rule: PricingRule;
  price: number;
  matched: boolean;
};

export type CalculationItem = {
  ruleSet: PricingRuleSet;
  strategy: string;
  items: CalculationItemDetail[];
};

export type CalculationOutput = {
  amount: number;
  tax: {
    amount: number;
    included: boolean;
  };
  ruleSet: PricingRuleSet;
  strategy: string;
  items: CalculationItem[];
  order: Order;
};

export type UpdateOrderRequest = {
  nodeId: string;
  state?: string;
  notes?: string;
  shippingTimeRange?: TsRange;
  fulfillmentMethod?: string;
  paymentMethod?: string;
  reusablePackagingEnabled?: boolean;
  reusablePackagingPledgeReturn?: number;
  reusablePackagingQuantity?: number;
};

export type PatchAddressRequest = AddressPayload & {
  '@id': Uri;
};

export type PostStoreAddressRequest = AddressPayload & {
  storeUri: Uri;
};

export type CalculatePriceRequest = PostDeliveryRequest;

export type SuggestOptimizationsRequest = {
  tasks: Task[];
  vehicle?: string;
};

export type ManualSupplementValues = {
  '@id': Uri;
  quantity: number;
};

export type OrderPayload = {
  manualSupplements: ManualSupplementValues[];
  arbitraryPrice?: {
    variantName: string;
    variantPrice: number;
  };
  isSavedOrder: boolean;
};

export type AddressPayload = {
  '@id'?: string;
  streetAddress: string;
  name: string;
  contactName: string;
  telephone: string | null;
  formattedTelephone?: string | null; // Form-specific field for display
  geo?: {
    latitude: number;
    longitude: number;
  };
  description?: string;
};

export type InputPackage = {
  '@id'?: string;
  name: string;
  type: string;
  quantity: number;
};

export type TaskPayload = {
  '@id'?: string;
  id?: number;
  createdAt?: string;
  updatedAt?: string;
  type: 'PICKUP' | 'DROPOFF';
  after: string;
  before: string;
  timeSlot: StoreTimeSlot | null;
  timeSlotUrl: string | null;
  comments: string;
  address: AddressPayload;
  updateInStoreAddresses?: boolean;
  saveInStoreAddresses?: boolean;
  packages: InputPackage[];
  weight: number;
  tags: Tag[];
};

export type PostDeliveryRequest = {
  store?: Uri;
  tasks: TaskPayload[];
  order?: OrderPayload;
  rrule?: string;
};

export type PutDeliveryRequest = {
  '@id': Uri;
  tasks?: TaskPayload[];
  order?: OrderPayload;
};

export type PutRecurrenceRuleRequest = {
  '@id': Uri;
  rule?: string;
  template?: DeliveryTemplate;
  store?: Uri;
  generateOrders?: boolean;
};

export type CreatePricingRuleSetRequest = {
  name: string;
  strategy?: string;
  // options?: Record<string, any>
  rules?: PricingRule[];
};

export type UpdatePricingRuleSetRequest = {
  id: number;
  name?: string;
  strategy?: string;
  // options?: Record<string, any>
  rules?: PricingRule[];
};

// For recurrenceRulesGenerateOrders, the parameter is a moment.js date object
export type RecurrenceRulesGenerateOrdersRequest = {
  format: (format: string) => string; // Moment.js date object with format method
};

export type OrderTiming = {
  preparation?: {
    expectedAt: string;
    time: string;
  };
  pickup?: {
    expectedAt: string;
    time: string;
  };
  dropoff?: {
    expectedAt: string;
    time: string;
  };
  shipping?: {
    time: string;
  };
};

export type OrderValidation = {
  valid: boolean;
  errors?: string[];
  warnings?: string[];
};

export type RecurrenceRulesGenerateOrdersResponse = {
  generated: number;
  orders: Order[];
};
