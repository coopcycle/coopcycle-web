// ProductOptionsModalContent pulls in FrequentlyBoughtTogether -> redux/actions,
// which transitively imports i18n/AddressAutosuggest (antd locales, react-leaflet)
// that aren't transformable in the Jest environment. Stub it out since this
// suite only exercises the pure getOffsets() helper.
jest.mock('../../../redux/actions', () => ({}))

import { getOffsets } from '../ProductOptionsModalContent'

describe('getOffsets', () => {

  it('with only mandatory options', () => {
    const options = [
      // 0, 0
      { additional: false, hasMenuItem: [ {}, {} ] },
      // 1, 1, 1
      { additional: false, hasMenuItem: [ {}, {}, {} ] },
      // 2, 2
      { additional: false, hasMenuItem: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 1, 2])
  })

  it('with only additional options', () => {
    const options = [
      // 0, 1
      { additional: true, hasMenuItem: [ {}, {} ] },
      // 2, 3, 4
      { additional: true, hasMenuItem: [ {}, {}, {} ] },
      // 5, 6
      { additional: true, hasMenuItem: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 2, 5])
  })

  it('with additional option after mandatory option', () => {
    const options = [
      // 0, 0
      { additional: false,  hasMenuItem: [ {}, {}, {} ] },
      // 1, 2
      { additional: true,   hasMenuItem: [ {}, {} ] },
      // 3, 3
      { additional: false,  hasMenuItem: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 1, 3])
  })

  it('with mandatory option after additional option', () => {
    const options = [
      // 0, 1
      { additional: true,   hasMenuItem: [ {}, {} ] },
      // 2, 2
      { additional: false,  hasMenuItem: [ {}, {}, {} ] },
      // 3, 3
      { additional: false,  hasMenuItem: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 2, 3])
  })

})
