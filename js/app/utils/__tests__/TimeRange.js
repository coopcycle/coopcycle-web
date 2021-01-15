import TimeRange from '../TimeRange'

describe('TimeRange.parse', () => {

  it('should return expected results', () => {

    expect(
      TimeRange.parse('Mo-Sa 12:00-12:30')
    ).toEqual({
      days: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
      start: '12:00',
      end: '12:30',
    })

    expect(
      TimeRange.parse('Mo 12:00-12:30')
    ).toEqual({
      days: ['Mo'],
      start: '12:00',
      end: '12:30',
    })

    expect(
      TimeRange.parse('Mo,We 12:00-12:30')
    ).toEqual({
      days: ['Mo', 'We'],
      start: '12:00',
      end: '12:30',
    })

    expect(
      TimeRange.parse('Mo,We-Fr 12:00-12:30')
    ).toEqual({
      days: ['Mo', 'We', 'Th', 'Fr'],
      start: '12:00',
      end: '12:30',
    })

  })

})

describe('TimeRange.getDayPart', () => {

  it('should return expected results', () => {

    expect(
      TimeRange.getDayPart('Mo-Sa 12:00-12:30')
    ).toEqual('Mo-Sa')

    expect(
      TimeRange.getDayPart('Mo 12:00-12:30')
    ).toEqual('Mo')

    expect(
      TimeRange.getDayPart('Mo,We 12:00-12:30')
    ).toEqual('Mo,We')

    expect(
      TimeRange.getDayPart('Mo,We-Fr 12:00-12:30')
    ).toEqual('Mo,We-Fr')

  })

})
