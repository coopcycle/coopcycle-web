import { getOffsets } from '../components/ProductOptionsModalContent'

describe('getOffsets', () => {

  it('with only mandatory options', () => {
    const options = [
      // 0, 0
      { additional: false, values: [ {}, {} ] },
      // 1, 1, 1
      { additional: false, values: [ {}, {}, {} ] },
      // 2, 2
      { additional: false, values: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 1, 2])
  })

  it('with only additional options', () => {
    const options = [
      // 0, 1
      { additional: true, values: [ {}, {} ] },
      // 2, 3, 4
      { additional: true, values: [ {}, {}, {} ] },
      // 5, 6
      { additional: true, values: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 2, 5])
  })

  it('with additional option after mandatory option', () => {
    const options = [
      // 0, 0
      { additional: false,  values: [ {}, {}, {} ] },
      // 1, 2
      { additional: true,   values: [ {}, {} ] },
      // 3, 3
      { additional: false,  values: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 1, 3])
  })

  it('with mandatory option after additional option', () => {
    const options = [
      // 0, 1
      { additional: true,   values: [ {}, {} ] },
      // 2, 2
      { additional: false,  values: [ {}, {}, {} ] },
      // 3, 3
      { additional: false,  values: [ {}, {} ] }
    ]
    expect(getOffsets(options)).toEqual([0, 2, 3])
  })

})
