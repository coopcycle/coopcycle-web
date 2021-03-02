import { formatAddress } from '../algolia'

describe('formatAddress', () => {

  it('should return expected results', () => {

    expect(
      formatAddress({
        locale_names: ['42 Rue de Rivoli'],
        postcode: [75004],
        city: ['Paris 4e Arrondissement'],
        county: ['Paris'],
        country: ['France'],
      }, 'city')
    ).toEqual('42 Rue de Rivoli, 75004 Paris 4e Arrondissement, France')

    expect(
      formatAddress({
        locale_names: ['42 Rue de Rivoli'],
        postcode: [75004],
        city: ['Paris 4e Arrondissement'],
        county: ['Paris'],
        country: ['France'],
      }, 'county')
    ).toEqual('42 Rue de Rivoli, 75004 Paris, France')

    expect(
      formatAddress({
        locale_names: ['Plaza del Ángel 16'],
        postcode: ['28001'],
        city: ['Madrid'],
        county: ['Área metropolitana de Madrid y Corredor del Henares'],
        country: ['España'],
      }, 'city,no-postcode')
    ).toEqual('Plaza del Ángel 16, Madrid, España')

    expect(
      formatAddress({
        locale_names: ['Plaza del Ángel 16'],
        postcode: ['28001'],
        city: ['Madrid'],
        county: ['Área metropolitana de Madrid y Corredor del Henares'],
        country: ['España'],
      }, 'no-postcode')
    ).toEqual('Plaza del Ángel 16, Madrid, España')

  })

  it('should return expected results with missing postcode', () => {

    expect(
      formatAddress({
        locale_names: ['42 Rue de Rivoli'],
        city: ['Paris 4e Arrondissement'],
        county: ['Paris'],
        country: ['France'],
      }, 'city')
    ).toEqual('42 Rue de Rivoli, Paris 4e Arrondissement, France')

    expect(
      formatAddress({
        locale_names: ['42 Rue de Rivoli'],
        city: ['Paris 4e Arrondissement'],
        county: ['Paris'],
        country: ['France'],
      }, 'county')
    ).toEqual('42 Rue de Rivoli, Paris, France')

  })

})
