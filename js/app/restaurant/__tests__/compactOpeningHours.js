import compactOpeningHours from '../compactOpeningHours'

describe('compactOpeningHours', () => {

  it('should return expected results', () => {

    expect(
      compactOpeningHours([
        'Mo-Sa 12:00-12:30',
        'Mo-Sa 12:30-13:00',
        'Mo-Sa 13:00-14:00',
        'Mo-Sa 19:00-19:30',
        'Mo-Sa 19:30-20:00',
      ])
    ).toEqual([
      'Mo-Sa 12:00-14:00',
      'Mo-Sa 19:00-20:00',
    ])

    expect(
      compactOpeningHours([
        'Mo 12:00-12:30',
        'Mo 12:30-13:00',
        'Mo 13:00-14:00',
        'Mo 19:00-19:30',
        'Mo 19:30-20:00',
      ])
    ).toEqual([
      'Mo 12:00-14:00',
      'Mo 19:00-20:00',
    ])

    expect(
      compactOpeningHours([
        'Mo-Sa 12:00-12:30',
        'Mo-Sa 12:30-13:00',
        'Mo-Sa 13:00-14:00',
        'Mo-Sa 19:00-19:30',
      ])
    ).toEqual([
      'Mo-Sa 12:00-14:00',
      'Mo-Sa 19:00-19:30',
    ])

    expect(
      compactOpeningHours([
        'Mo,Sa-Su 12:00-12:30',
        'Mo,Sa-Su 12:30-13:00',
        'Mo,Sa-Su 13:00-14:00',
        'Mo,Sa-Su 19:00-19:30',
        'Mo,Sa-Su 19:30-20:00',
      ])
    ).toEqual([
      'Mo,Sa-Su 12:00-14:00',
      'Mo,Sa-Su 19:00-20:00',
    ])

    expect(
      compactOpeningHours([
        'Mo 12:00-12:30',
        'Mo 12:30-13:00',
        'Mo 13:00-14:00',
        'Sa-Su 19:00-19:30',
        'Sa-Su 19:30-20:00',
      ])
    ).toEqual([
      'Mo 12:00-14:00',
      'Sa-Su 19:00-20:00',
    ])

    // The array is not sorted
    // Moreover, some items are not consecutive
    expect(
      compactOpeningHours([
        "Mo-Fr 12:00-12:30",
        "Mo-Fr 12:15-12:45",
        "Mo-Fr 12:30-13:00",
        "Mo-Fr 12:45-13:15",
        "Mo-Fr 13:00-13:30",
        "Mo-Fr 13:15-13:45",
        "Mo-Fr 13:30-14:00",
        // The item below is before the previous item
        "Mo-Fr 10:00-10:30",
        "Mo-Fr 10:15-10:45",
        "Mo-Fr 10:30-11:00",
        "Mo-Fr 11:00-11:30",
        "Mo-Fr 10:45-11:15",
        "Mo-Fr 11:15-11:45",
        "Mo-Fr 11:30-12:00",
        "Mo-Fr 11:45-12:15",
        "Mo-Fr 13:45-14:15",
        "Mo-Fr 14:00-14:30",
        "Mo-Fr 14:15-14:45",
        "Mo-Fr 14:30-15:00",
        "Mo-Fr 14:45-15:15",
        "Mo-Fr 15:00-15:30",
        "Mo-Fr 15:15-15:45",
        "Mo-Fr 15:30-16:00",
        "Mo-Fr 15:45-16:15",
        "Mo-Fr 16:00-16:30",
        "Mo-Fr 16:15-16:45",
        "Mo-Fr 16:30-17:00",
        "Mo-Fr 16:45-17:15",
        "Mo-Fr 17:00-17:30",
        "Mo-Fr 17:15-17:45",
        "Mo-Fr 17:30-18:00",
        "Mo-Fr 17:45-18:15",
        "Mo-Fr 18:00-18:30",
        "Mo-Fr 18:15-18:45",
        // There is no item "Mo-Fr 18:45-19:00"
        "Mo-Fr 18:30-19:00"])
    ).toEqual([
      'Mo-Fr 10:00-18:45',
      'Mo-Fr 18:30-19:00',
    ])

  })

})
