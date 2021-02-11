import { storage, getFromCache } from '../cache'

const cachedResults = {
  '4 av victoria': [
    { type: 'prediction', value: '4, Avenue Victoria Paris 4' }
  ],
  '4 av victoria paris': [
    { type: 'prediction', value: '4, Avenue Victoria Paris 4' }
  ],
  '4 av victoria paris 4': [
    { type: 'prediction', value: '4, Avenue Victoria Paris 4' }
  ],
  '12 rue de rivoli, paris': [
    { type: 'prediction', value: '12, Rue de Rivoli Paris 1' }
  ]
}

describe('AddressAutosuggest/cache', () => {

  beforeEach(() => {
    storage.clearAll()
    for (let search in cachedResults) {
      storage.set(search, cachedResults[search])
    }
  })

  it('should return expected results', () => {

    expect(getFromCache('4 av victoria paris 4 bat B')).toStrictEqual(cachedResults['4 av victoria paris 4'])
    expect(getFromCache('4 av victoria paris')).toStrictEqual(cachedResults['4 av victoria paris'])

    expect(getFromCache('av victoria paris')).toEqual([])
    expect(getFromCache('12 av victoria paris')).toEqual([])
  })

})
