import * as React from 'react'
import { useCombobox } from 'downshift'
import cx from 'classnames'
import axios from 'axios'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

const search = _.debounce((inputValue, setItems, setLoading) => {

  if (!inputValue) {
    setItems([])
    return
  }

  if (inputValue.length < 3) {
    return
  }

  setLoading(true)

  window._paq.push(['trackEvent', 'Search', 'search', inputValue])

  axios
    .get(`/api/search/shops_products?q=${encodeURIComponent(inputValue)}`)
    .then((response) => {
      setItems(response.data['hydra:member'])
      setLoading(false)
    })

}, 300)

// https://github.com/downshift-js/downshift/issues/167#issuecomment-326642420
// https://codesandbox.io/s/zx1kj58npl?file=/index.js:1866-1878

const Section = withTranslation()(({ items, title, highlightedIndex, getItemProps, offset, t }) => {

  return (
    <>
      <h5 className="p-3 font-bold">
        { title }
      </h5>
      <ul>
        { items.map((item, index) => {

          const indexWithOffset = index + offset

          return (
            <li
              className={cx(
                'cursor-pointer',
                'py-2 px-3',
                highlightedIndex === indexWithOffset && 'bg-sky-50',
              )}
              key={`search-result-${index}`}
              { ...getItemProps({ item, index: indexWithOffset }) }
            >
              <span className="text-sm">{ item.name }</span>
              { item.result_type === 'product' &&
                <>
                  <br />
                  <small className="text-xs">{ t('SEARCH_PRODUCT_ITEM_SUBTITLE', { shop: item.shop_name }) }</small>
                </>
              }
            </li>
          )
        }) }
      </ul>
    </>
  )
})

function Sections({ sections, highlightedIndex, getItemProps }) {

  return sections.reduce((result, section, sectionIndex) => {

    result.sections.push(
      <Section
        key={ `section-${sectionIndex}` }
        title={ section.title }
        items={ section.items }
        highlightedIndex={ highlightedIndex }
        getItemProps={ getItemProps }
        offset={ result.offset } />
    )

    result.offset += section.items.length

    return result

  }, { sections: [], offset: 0 }).sections
}

function ComboBox({ t }) {

  const [ items, setItems ] = React.useState([])
  const [ isLoading, setLoading ] = React.useState(false)

  const {
    isOpen,
    getMenuProps,
    getInputProps,
    highlightedIndex,
    getItemProps,
  } = useCombobox({
    onInputValueChange({ inputValue }) {
      search(inputValue, setItems, setLoading)
    },
    onSelectedItemChange(changes) {
      const selectedItem = changes.selectedItem
      window._paq.push(['trackEvent', 'Search', 'selectItem', selectedItem.name])
      window.location.href = window.Routing.generate('restaurant', {
        id: selectedItem.result_type === 'product' ? selectedItem.shop_id : selectedItem.id
      })
    },
    items,
    itemToString(item) {
      return item ? item.title : ''
    },
  })

  const itemsByResultType = _.groupBy(items, item => item.result_type)
  const sections = []
  if (itemsByResultType.shop) {
    sections.push({
      title: t('SEARCH_SHOPS'),
      items: itemsByResultType.shop,
    })
  }
  if (itemsByResultType.product) {
    sections.push({
      title: t('SEARCH_PRODUCTS'),
      items: itemsByResultType.product,
    })
  }

  // TODO Re-add loading
  return (
    <>
      <div className={cx('relative', /*isLoading && 'has-loader-loading'*/)}>
        {/* FIXME
        The "label" HTML tag style is overriden by Bootstrap in AddressAutosuggest
        Use a span atm */}
        <span className="input input-bordered bg-base-100 text-base-content w-24 md:w-auto">
          <i className="fa fa-search"></i>
          <input
            placeholder={t('SEARCH_PLACEHOLDER')}
            type="search"
            { ...getInputProps() }
            />
        </span>
      </div>
      <div
        {...getMenuProps()}
        className={cx(
          (isOpen && items.length > 0) && 'border border-gray-200 rounded-b-md',
          'absolute h-screen max-h-[60vh] overflow-auto z-2 bg-base-100'
        )}
      >
        { isOpen &&
          <Sections
            sections={ sections }
            highlightedIndex={ highlightedIndex }
            getItemProps={ getItemProps } />
        }
      </div>
    </>
  )
}

export default withTranslation()(ComboBox)
