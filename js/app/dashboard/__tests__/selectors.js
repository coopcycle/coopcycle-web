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

const th = {
  rule: 'FREQ=WEEKLY;BYDAY=TH',
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

    it('sorts matching rules by name, then orgName for unnamed rules', () => {
      const alpha = {
        ...th,
        '@id': '/api/recurrence_rules/11',
        name: 'Alpha',
        orgName: 'Zulu'
      }
      const zeta = {
        ...th,
        '@id': '/api/recurrence_rules/12',
        name: 'zeta',
        orgName: 'Alpha'
      }
      const unnamedAcme = {
        ...th,
        '@id': '/api/recurrence_rules/13',
        orgName: 'Acme'
      }
      const unnamedBeta = {
        ...th,
        '@id': '/api/recurrence_rules/14',
        orgName: 'Beta'
      }

      const result = selectRecurrenceRules({
        logistics: {
          date: "2021-03-04T23:00:00.000Z",
        },
        rrules: recurrenceRulesAdapter.upsertMany(
          recurrenceRulesAdapter.getInitialState(),
          [
            unnamedBeta,
            zeta,
            unnamedAcme,
            alpha
          ]
        ),
      })

      expect(result.map(rule => rule['@id'])).toEqual([
        '/api/recurrence_rules/11',
        '/api/recurrence_rules/12',
        '/api/recurrence_rules/13',
        '/api/recurrence_rules/14',
      ])
    })

    it('uses @id as deterministic tie-breaker', () => {
      const first = {
        ...th,
        '@id': '/api/recurrence_rules/20',
        name: 'Same Name',
        orgName: 'Same Org'
      }
      const second = {
        ...th,
        '@id': '/api/recurrence_rules/21',
        name: 'Same Name',
        orgName: 'Same Org'
      }

      const result = selectRecurrenceRules({
        logistics: {
          date: "2021-03-04T23:00:00.000Z",
        },
        rrules: recurrenceRulesAdapter.upsertMany(
          recurrenceRulesAdapter.getInitialState(),
          [
            second,
            first
          ]
        ),
      })

      expect(result.map(rule => rule['@id'])).toEqual([
        '/api/recurrence_rules/20',
        '/api/recurrence_rules/21',
      ])
    })
  })
})
