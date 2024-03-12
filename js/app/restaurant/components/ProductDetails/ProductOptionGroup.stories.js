import React from 'react'
import { OptionGroup } from './ProductOptionGroup'
import { ProductOptionsModalProvider } from './ProductOptionsModalContext'
import i18n from '../../../i18n'
import { I18nextProvider } from 'react-i18next'

export default {
  title: 'Foodtech/3. Product Details/5. Options',
  tags: ['autodocs'],
  component: OptionGroup,
  decorators: [
    (Story, context) => (
      <I18nextProvider i18n={i18n}>
        <div className="ReactModal__Content--product-options">
          <ProductOptionsModalProvider
            options={context.args.options}
            price={context.args.price}>

            <div className="product-modal-container">
              {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */}
              <Story/>
            </div>

          </ProductOptionsModalProvider>
        </div>
      </I18nextProvider>
    ),
  ],
}

export const FreeSingleChoiceOption = {
  args: {
    name: 'Pizza',
    options: [],
    index: 0,
    option:
      {
        'strategy': 'free',
        'additional': false,
        'valuesRange': null,
        'code': 'bc97885d-2c53-49e3-9476-cafabd7e2e33',
        'values': [
          {
            'price': 0,
            'code': '398aeb18-7ed3-4e1b-8792-d6c3b364d4b4',
            'enabled': true,
            'value': 'Vegana',
          },
          {
            'price': 0,
            'code': 'e0474ed9-1482-4561-9c71-12c5ad014b79',
            'enabled': true,
            'value': 'Vegetariana',
          },
          {
            'price': 0,
            'code': 'e0474ed9-1482-4561-9c71-12c5ad014b79',
            'enabled': true,
            'value': 'Loren ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus feugiat, vestibulum ligula sit amet, posuere urna.',
          }],
        'name': 'Vegana o Vegetariana',
      },
  },
  optionInApiFormat: {
    '@type': 'MenuSection',
    'name': 'Vegana o Vegetariana',
    'identifier': 'bc97885d-2c53-49e3-9476-cafabd7e2e33',
    'additionalType': 'free',
    'additional': false,
    'hasMenuItem': [
      {
        '@type': 'MenuItem',
        'name': 'Vegana',
        'identifier': '398aeb18-7ed3-4e1b-8792-d6c3b364d4b4',
        'offers': { '@type': 'Offer', 'price': 0 },
      },
      {
        '@type': 'MenuItem',
        'name': 'Vegetariana',
        'identifier': 'e0474ed9-1482-4561-9c71-12c5ad014b79',
        'offers': { '@type': 'Offer', 'price': 0 },
      }],
  },
}

export const PaidSingleChoiceOption = {
  args: {
    name: 'Pizza',
    options: [],
    index: 0,
    option:
      {
        'strategy': 'option_value',
        'additional': false,
        'valuesRange': null,
        'code': '9b5f9886-aaf1-3942-a6c2-0d5dcdf64cd1',
        'values': [
          {
            'price': 50,
            'code': '96a6c7ff-12b6-38b0-81d2-5bc7e74398c3',
            'enabled': true,
            'value': 'Frites',
          },
          {
            'price': 50,
            'code': 'd1af196c-e1d4-32fe-990a-26304baf65bb',
            'enabled': true,
            'value': 'Salade',
          },
          {
            'price': 50,
            'code': '47234d17-3741-348a-8411-ccc3701cf137',
            'enabled': true,
            'value': 'Carottes r\u00e2p\u00e9es',
          },
          {
            'price': 50,
            'code': '3eec32ce-2606-34fd-a4f2-51e7e5e8a434',
            'enabled': true,
            'value': 'Salade',
          },
          {
            'price': 50,
            'code': '185a42a4-74fd-37e5-b3a9-5aa0874d48be',
            'enabled': true,
            'value': 'Loren ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus feugiat, vestibulum ligula sit amet, posuere urna.',
          }],
        'name': 'Accompagnement',
      },
  },
}

export const FreeAdditionalOption = {
  args: {
    name: 'Pizza',
    options: [],
    index: 0,
    option:
      {
        'strategy': 'free',
        'additional': true,
        'valuesRange': { 'lower': '0', 'upper': 5, 'isUpperInfinite': false },
        'code': 'fc223477-46b2-3c24-be12-1b9d4812e43e',
        'values': [
          {
            'price': 0,
            'code': 'bd11dc33-8ad6-3cab-adbb-24fd11977d62',
            'enabled': true,
            'value': 'Th\u00e9 vert',
          },
          {
            'price': 0,
            'code': 'df480792-d5d2-3a87-9aa9-b5bbbdf947ad',
            'enabled': true,
            'value': 'Loren ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus feugiat, vestibulum ligula sit amet, posuere urna.',
          },
          {
            'price': 0,
            'code': 'e2d922dc-5e3c-338d-b734-52eb205e8f26',
            'enabled': true,
            'value': 'Soda',
          }],
        'name': 'Boisson',
      },
  },
}

export const PaidAdditionalOption = {
  args: {
    name: 'Pizza',
    options: [],
    index: 0,
    option:
      {
        'strategy': 'option_value',
        'additional': true,
        'valuesRange': { 'lower': '0', 'upper': null, 'isUpperInfinite': true },
        'code': 'fc223477-46b2-3c24-be12-1b9d4812e43e',
        'values': [
          {
            'price': 100,
            'code': 'bd11dc33-8ad6-3cab-adbb-24fd11977d62',
            'enabled': true,
            'value': 'Th\u00e9 vert',
          },
          {
            'price': 100,
            'code': 'df480792-d5d2-3a87-9aa9-b5bbbdf947ad',
            'enabled': true,
            'value': 'Loren ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus feugiat, vestibulum ligula sit amet, posuere urna.',
          },
          {
            'price': 100,
            'code': 'e2d922dc-5e3c-338d-b734-52eb205e8f26',
            'enabled': true,
            'value': 'Soda',
          }],
        'name': 'Boisson',
      },
  },
}
