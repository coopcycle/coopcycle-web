import {
  selectRecurrenceRules,
  recurrenceRulesAdapter,
} from '../redux/selectors'

const thFr = {
  '@id': '/api/recurrence_rules/1',
  rule: 'FREQ=WEEKLY;BYDAY=TH,FR',
  orgName: 'Acme',
  template: {
    '@type': 'hydra:Collection',
    'hydra:member': [
      {
        after: '10:00',
        before: '11:00'
      }
    ]
  }
}

const mo = {
  '@id': '/api/recurrence_rules/2',
  rule: 'FREQ=WEEKLY;BYDAY=MO',
  orgName: 'Acme',
  template: {
    '@type': 'hydra:Collection',
    'hydra:member': [
      {
        after: '11:00',
        before: '12:00'
      }
    ]
  }
}

const th2359 = {
  '@id': '/api/recurrence_rules/1',
  rule: 'FREQ=WEEKLY;BYDAY=TH',
  orgName: 'Acme',
  template: {
    '@type': 'hydra:Collection',
    'hydra:member': [
      {
        after: '00:00',
        before: '23:59'
      }
    ]
  }
}

describe('Selectors', () => {

  describe('selectRecurrenceRules', () => {
    it('returns empty list', () => {
      expect(selectRecurrenceRules({
        logistics: {
          date: "2021-03-04T23:00:00.000Z",
        },
        rrules: recurrenceRulesAdapter.getInitialState(),
      })).toEqual([])
    })

    it('returns matching rules', () => {
      expect(selectRecurrenceRules({
        logistics: {
          date: "2021-03-04T23:00:00.000Z",
        },
        rrules: recurrenceRulesAdapter.upsertMany(
          recurrenceRulesAdapter.getInitialState(),
          [
            thFr, mo
          ]
        ),
      })).toEqual([ thFr ])
    })

    it('returns matching rules (bounds included)', () => {
      expect(selectRecurrenceRules({
        logistics: {
          date: "2021-03-04T23:00:00.000Z",
        },
        rrules: recurrenceRulesAdapter.upsertMany(
          recurrenceRulesAdapter.getInitialState(),
          [
            th2359, mo
          ]
        ),
      })).toEqual([ th2359 ])
    })
  })
})
