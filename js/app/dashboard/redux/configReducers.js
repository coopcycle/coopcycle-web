const initialState = {
  centrifugoToken: '',
  centrifugoTrackingChannel: '$coopcycle_tracking',
  centrifugoEventsChannel: 'coopcycle_events',
  stores: [],
  tags: [],
  uploaderEndpoint: '',
  exampleSpreadsheetUrl: '#',
  couriersList: [],
  nav: '',
  pickupClusterAddresses: [],
}

export default (state = initialState) => state
