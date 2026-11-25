const initialState = {
  centrifugoToken: '',
  centrifugoTrackingChannel: '$coopcycle_tracking',
  centrifugoEventsChannel: 'coopcycle_events',
  stores: [],
  tags: [],
  couriersList: [],
  nav: '',
  initialTask: null,
  pickupClusterAddresses: [],
  exportEnabled: 'off',
  precogEnabled: false,
}

export default (state = initialState) => state
