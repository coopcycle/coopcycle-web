import React, { useEffect, useState, useRef, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import { useTranslation } from 'react-i18next';

import { Button, Form, Modal, Typography, Input } from 'antd';
import { EditOutlined } from '@ant-design/icons';
const { Text } = Typography;

// https://blog.logrocket.com/implement-pragmatic-drag-drop-library-guide/
import {
  draggable,
  dropTargetForElements,
  monitorForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import { attachClosestEdge, extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';
import { getReorderDestinationIndex } from "@atlaskit/pragmatic-drag-and-drop-hitbox/util/get-reorder-destination-index";
import { reorder } from "@atlaskit/pragmatic-drag-and-drop/reorder"

import _ from 'lodash';
import { Provider, useDispatch, useSelector } from 'react-redux'

import { createStoreFromPreloadedState } from './menu-editor/store'
import {
  fetchProducts,
  removeProductFromSection,
  setSectionProducts,
  moveProductToSection,
  updateSectionsOrder,
  openModal,
  closeModal,
  addSection,
  updateSection,
  deleteSection,
  setMenuName,
  createSectionFlow,
  editSectionFlow,
} from './menu-editor/actions'
import {
  selectProducts,
  selectMenuSections,
  selectIsModalOpen,
  selectMenuName,
  selectSectionInModal,
} from './menu-editor/selectors'

import './menu-editor.scss'

const httpClient = new window._auth.httpClient()

const Section = ({ section }) => {

  const dispatch = useDispatch();

  const dropTargetRef = useRef(null);
  const draggableRef = useRef(null);
  const dragHandleRef = useRef(null);

  const [ isDraggedOver, setIsDraggedOver ] = useState(false);
  const [ isDragging, setIsDragging ] = useState(false);

  // State to track the closest edge during drag over
  const [closestEdge, setClosestEdge] = useState(null);

  useEffect(() => {

    const dropTargetEl = dropTargetRef.current;
    const draggableEl = draggableRef.current;
    const dragHandleEl = dragHandleRef.current;

    return combine(
      dropTargetForElements({
        element: dropTargetEl,
        onDragStart: () => setIsDraggedOver(true),
        onDragEnter: () => setIsDraggedOver(true),
        onDragLeave: () => setIsDraggedOver(false),
        onDrop: () => setIsDraggedOver(false),
        // getData: () => ({ sectionId: section['@id'] }),
        getData: ({ input, element }) => {
          // To attach card data to a drop target
          const data = { type: "section", sectionId: section['@id'] };

          // Attaches the closest edge (top or bottom) to the data object
          // This data will be used to determine where to drop card relative
          // to the target card.
          return attachClosestEdge(data, {
            input,
            element,
            allowedEdges: ["top", "bottom"],
          });
        },
        getIsSticky: () => true,
        onDragEnter: (args) => {
          // Update the closest edge when a draggable item enters the drop zone
          if (args.source.data.type === 'section' && args.source.data.sectionId !== section['@id']) {
            setClosestEdge(extractClosestEdge(args.self.data));
          }
        },
        onDrag: (args) => {
          // Continuously update the closest edge while dragging over the drop zone
          if (args.source.data.type === 'section' && args.source.data.sectionId !== section['@id']) {
            setClosestEdge(extractClosestEdge(args.self.data));
          }
        },
        onDragLeave: () => {
          // Reset the closest edge when the draggable item leaves the drop zone
          setClosestEdge(null);
        },
        onDrop: () => {
          // Reset the closest edge when the draggable item is dropped
          setClosestEdge(null);
        },
      }),
      draggable({
        element: draggableEl,
        // https://atlassian.design/components/pragmatic-drag-and-drop/core-package/adapters/element/about#drag-handles
        dragHandle: dragHandleEl,
        getInitialData: () => ({ type: 'section', sectionId: section['@id'] }),
        onDragStart: () => setIsDragging(true),
        onDrop: () => setIsDragging(false),
      })
    );
  }, [section['@id']]);

  return (
    <div className="menuEditor__panel mb-4" ref={draggableRef}>
      <h4 className="menuEditor__panel__title">
        <div className="d-flex align-items-center">
          <i className="fa fa-arrows mr-2" ref={dragHandleRef}></i>
          <Text>{section.name}</Text>
          <Button type="link" icon={<EditOutlined />} onClick={() => dispatch(editSectionFlow(section))}></Button>
        </div>
        <a className="pull-right" href="#" onClick={ (e) => {
          e.preventDefault();
          dispatch(deleteSection(section));
        }}>
          <i className="fa fa-close"></i>
        </a>
      </h4>
      <div className={`menuEditor__panel__body ${isDraggedOver ? "menuEditor__panel__body--dragged" : ""}`} ref={dropTargetRef}>
        { section.hasMenuItem.map((product) => (
          <Product key={ product['@id'] } product={ product } />
        )) }
      </div>
      {/* render the DropIndicator if there's a closest edge */}
      {closestEdge && <DropIndicator edge={closestEdge} gap="8px" />}
    </div>
  )
}

const LeftPanel = () => {

  const { t } = useTranslation();
  const dispatch = useDispatch();
  const sections = useSelector(selectMenuSections)

  return (
    <div className="menuEditor__left">
      { sections.map((section, index) => (
        <Section key={`section-${index}`} section={section} index={ index } />
      ))}
      <div className="d-flex flex-row align-items-center justify-content-end border-top pt-4">
        <button type="button" className="btn btn-success" onClick={ () => dispatch(createSectionFlow()) }>
          <i className="fa fa-plus mr-2"></i><span>{t('MENU_EDITOR.ADD_SECTION')}</span>
        </button>
      </div>
    </div>
  )
}

const RightPanel = () => {

  const ref = useRef(null);
  const products = useSelector(selectProducts)

  const [ isDraggedOver, setIsDraggedOver ] = useState(false);

  useEffect(() => {
    const el = ref.current;

    return dropTargetForElements({
      element: el,
      onDragStart: () => setIsDraggedOver(true),
      onDragEnter: () => setIsDraggedOver(true),
      onDragLeave: () => setIsDraggedOver(false),
      onDrop: () => setIsDraggedOver(false),
      getData: () => ({ sectionId: 'products' }),
      getIsSticky: () => true,
    });
  }, [ products ]);

  return (
    <div className="menuEditor__right">
      <div className="menuEditor__panel menuEditor__productList">
        <h4 className="menuEditor__panel__title">
          Products {/*{{ 'form.menu_editor.products_panel.title'|trans }}*/}
        </h4>
        <div className={`menuEditor__panel__body ${isDraggedOver ? "menuEditor__panel__body--dragged" : ""}`} ref={ref}>
          { products.map((product, index) => (
            <Product key={ `product-${index}` } product={ product } />
          )) }
        </div>
      </div>
    </div>
  )
}

const Product = ({ product }) => {

  const [ isDragging, setIsDragging ] = useState(false);
  // State to track the closest edge during drag over
  const [closestEdge, setClosestEdge] = useState(null);

  const ref = useRef(null);

  useEffect(() => {
    const el = ref.current;

    return combine(
      draggable({
        element: el,
        getInitialData: () => ({ type: 'product', productId: product['@id'] }),
        onDragStart: () => setIsDragging(true),
        onDrop: () => setIsDragging(false),
      }),
      // Add dropTargetForElements to make the product a drop target
      dropTargetForElements({
        element: el,
        getData: ({ input, element }) => {
          // To attach card data to a drop target
          const data = { type: "product", productId: product['@id'] };

          // Attaches the closest edge (top or bottom) to the data object
          // This data will be used to determine where to drop card relative
          // to the target card.
          return attachClosestEdge(data, {
            input,
            element,
            allowedEdges: ["top", "bottom"],
          });
        },
        getIsSticky: () => true, // To make a drop target "sticky"
        onDragEnter: (args) => {
          // Update the closest edge when a draggable item enters the drop zone
          if (args.source.data.type === 'product' && args.source.data.productId !== product['@id']) {
            setClosestEdge(extractClosestEdge(args.self.data));
          }
        },
        onDrag: (args) => {
          // Continuously update the closest edge while dragging over the drop zone
          if (args.source.data.type === 'product' && args.source.data.productId !== product['@id']) {
            setClosestEdge(extractClosestEdge(args.self.data));
          }
        },
        onDragLeave: () => {
          // Reset the closest edge when the draggable item leaves the drop zone
          setClosestEdge(null);
        },
        onDrop: () => {
          // Reset the closest edge when the draggable item is dropped
          setClosestEdge(null);
        },
      })
    );
  }, [ product['@id'] ]);

  return (
    <div className="menuEditor__product" ref={ref}>
      { product.name }
      {/* render the DropIndicator if there's a closest edge */}
      {closestEdge && <DropIndicator edge={closestEdge} gap="8px" />}
    </div>
  )
}

const DropIndicator = ({ edge, gap }) => {
  const edgeClassMap = {
    top: "edge-top",
    bottom: "edge-bottom",
  };

  const edgeClass = edgeClassMap[edge];

  const style = {
    "--gap": gap,
  };

  return <div className={`drop-indicator ${edgeClass}`} style={style}></div>;
};

const MenuNameForm = () => {

  const [form] = Form.useForm();

  const dispatch = useDispatch();
  const menuName = useSelector(selectMenuName);

  return (
    <Form
      layout="inline"
      form={form}
      initialValues={{ name: menuName }}
      onFinish={ (values) => dispatch(setMenuName(values.name)) }
    >
      <Form.Item label="Name" name="name">
        <Input />
      </Form.Item>
      <Form.Item>
        <Button type="primary" htmlType="submit">Submit</Button>
      </Form.Item>
    </Form>
  )
}

const SectionModal = () => {

  const dispatch = useDispatch();
  const { t } = useTranslation();
  const [form] = Form.useForm();

  const isModalOpen = useSelector(selectIsModalOpen);
  const section = useSelector(selectSectionInModal)

  const isNewSection = !Object.prototype.hasOwnProperty.call(section, '@id')

  // https://5x.ant.design/components/form#form-demo-form-in-modal
  return (
    <Modal
      open={ isModalOpen }
      onCancel={ () => dispatch(closeModal()) }
      okButtonProps={{ autoFocus: true, htmlType: 'submit' }}
      destroyOnHidden
      modalRender={(children) => (
        <Form
          layout="vertical"
          form={form}
          name="section"
          initialValues={section}
          clearOnDestroy
          onFinish={ (values) => dispatch(isNewSection ? addSection(values.name, values.description) : updateSection(section['@id'], values.name, values.description)) }
        >
          {children}
        </Form>
      )}>
      <Form.Item
        name="name"
        label={t('MENU_EDITOR.SECTION_NAME_LABEL')}
        rules={[{ required: true }]}
      >
        <Input placeholder={t('MENU_EDITOR.SECTION_NAME_PLACEHOLDER')} />
      </Form.Item>

      <Form.Item
        name="description"
        label={t('MENU_EDITOR.SECTION_DESCRIPTION_LABEL')}
      >
        <Input />
      </Form.Item>
    </Modal>
  )
}

const MenuEditor = ({ restaurant }) => {

  const dispatch = useDispatch();
  const { t } = useTranslation();

  useEffect(() => {
    dispatch(fetchProducts(restaurant));
  }, [dispatch])

  const sections = useSelector(selectMenuSections)
  const products = useSelector(selectProducts)

  const reorderSections = useCallback(
    ({ startIndex, finishIndex }) => {

      const updatedItems = reorder({
        list: sections,
        startIndex,
        finishIndex,
      });

      dispatch(updateSectionsOrder(updatedItems))
    },
    [sections]
  );

  const reorderProduct = useCallback(
    ({ sectionId, startIndex, finishIndex }) => {

      const section = _.find(sections, (s) => s['@id'] === sectionId);

      const updatedItems = reorder({
        list: section.hasMenuItem,
        startIndex,
        finishIndex,
      });

      dispatch(setSectionProducts(sectionId, updatedItems))
    },
    [sections]
  );

  const moveProduct = useCallback(
    ({
      movedProductIndexInSourceSection,
      sourceSectionId,
      destinationSectionId,
      movedProductIndexInDestinationSection,
    }) => {

      const sourceItems = sourceSectionId === 'products' ? products : _.find(sections, (s) => s['@id'] === sourceSectionId).hasMenuItem;
      const productToMove = sourceItems[movedProductIndexInSourceSection];

      // Moved from section back to right column
      if (destinationSectionId === 'products') {
        dispatch(removeProductFromSection(productToMove['@id']));
        return;
      }

      // Moved from a section to another section
      // Determine the new index in the destination section
      const newIndexInDestination = movedProductIndexInDestinationSection ?? 0;
      dispatch(moveProductToSection(productToMove, newIndexInDestination, destinationSectionId));

    },
    [sections, products]
  );

  // Function to handle drop events
  const handleDrop = useCallback(({ source, location }) => {

    // Early return if there are no drop targets in the current location
    const destination = location.current.dropTargets.length;
    if (!destination) {
      return;
    }

    // Check if the source of the drag is a card to handle card-specific logic
    if (source.data.type === "product") {

      // Retrieve the ID of the card being dragged
      const draggedProductId = source.data.productId;

      // Get the source column from the initial drop targets
      const [, sourceSectionRecord] = location.initial.dropTargets;

      // Retrieve the ID of the source column
      const sourceSectionId = sourceSectionRecord.data.sectionId;

      // Get the data of the source column
      const sourceSectionData = _.find(sections, (s) => s['@id'] === sourceSectionId);

      const sourceItems = sourceSectionId === 'products' ? products : sourceSectionData.hasMenuItem;

      // Get the index of the card being dragged in the source column
      const draggedProductIndex = sourceItems.findIndex(
        (product) => product['@id'] === draggedProductId
      );

      // Reordering within a column by dropping in an empty space
      if (location.current.dropTargets.length === 1) {

        // Get the destination column from the current drop targets
        const [destinationSectionRecord] = location.current.dropTargets;

        // Retrieve the ID of the destination column
        const destinationSectionId = destinationSectionRecord.data.sectionId;

        // check if the source and destination columns are the same
        if (sourceSectionId === destinationSectionId) {

          // Calculate the destination index for the dragged card within the same column
          const destinationIndex = getReorderDestinationIndex({
            startIndex: draggedProductIndex,
            indexOfTarget: sourceItems.length - 1,
            closestEdgeOfTarget: null,
            axis: "vertical",
          });

          reorderProduct({
            sectionId: sourceSectionData.sectionId,
            startIndex: draggedProductIndex,
            finishIndex: destinationIndex,
          });

          return;
        }

        // When columns are different, move the card to the new column
        moveProduct({
          movedProductIndexInSourceSection: draggedProductIndex,
          sourceSectionId,
          destinationSectionId,
        });

        return;
      }

      if (location.current.dropTargets.length === 2) {

        // Destructure and extract the destination card and column data from the drop targets
        const [destinationProductRecord, destinationSectionRecord] =
          location.current.dropTargets;

        // Extract the destination column ID from the destination column data
        const destinationSectionId = destinationSectionRecord.data.sectionId;

        if (sourceSectionId === 'products' && destinationSectionId === 'products') {
          // Do nothing when reordering right column
          return;
        }

        // Retrieve the destination column data using the destination column ID
        const destinationItems = destinationSectionId === 'products' ? products : _.find(sections, (s) => s['@id'] === destinationSectionId).hasMenuItem;

        // Find the index of the target card within the destination column's cards
        const indexOfTarget = destinationItems.findIndex(
          (product) => product['@id'] === destinationProductRecord.data.productId
        );

        // Determine the closest edge of the target card: top or bottom
        const closestEdgeOfTarget = extractClosestEdge(
          destinationProductRecord.data
        );

        // Check if the source and destination columns are the same
        if (sourceSectionId === destinationSectionId) {
          // Calculate the destination index for the card to be reordered within the same column
          const destinationIndex = getReorderDestinationIndex({
            startIndex: draggedProductIndex,
            indexOfTarget,
            closestEdgeOfTarget,
            axis: "vertical",
          });

          // Perform the card reordering within the same column
          reorderProduct({
            sectionId: sourceSectionId,
            startIndex: draggedProductIndex,
            finishIndex: destinationIndex,
          });

          return;
        }

        // Determine the new index for the moved card in the destination column.
        const destinationIndex =
          closestEdgeOfTarget === "bottom"
            ? indexOfTarget + 1
            : indexOfTarget;

        moveProduct({
          movedProductIndexInSourceSection: draggedProductIndex,
          sourceSectionId,
          destinationSectionId,
          movedProductIndexInDestinationSection: destinationIndex,
        });
      }
    }

    if (source.data.type === "section") {

      const draggedSectionId = source.data.sectionId;

      if (location.current.dropTargets.length === 2) {
        // Destructure and extract the destination card and column data from the drop targets
        const [destinationProductRecord, destinationSectionRecord] = location.current.dropTargets;

        // Extract the destination column ID from the destination column data
        const destinationSectionId = destinationSectionRecord.data.sectionId;

        const draggedSectionIndex = sections.findIndex(
          (section) => section['@id'] === draggedSectionId
        );

        // Find the index of the target card within the destination column's cards
        const indexOfTarget = sections.findIndex(
          (section) => section['@id'] === destinationSectionRecord.data.sectionId
        );

        // Determine the closest edge of the target card: top or bottom
        const closestEdgeOfTarget = extractClosestEdge(
          destinationSectionRecord.data
        );

        const destinationIndex = getReorderDestinationIndex({
          startIndex: draggedSectionIndex,
          indexOfTarget,
          closestEdgeOfTarget,
          axis: "vertical",
        });

        if (draggedSectionIndex !== destinationIndex) {
          reorderSections({
            startIndex: draggedSectionIndex,
            finishIndex: destinationIndex,
          });
        }

      }
    }

  }, [ sections, products ]);

  // setup the monitor
  useEffect(() => {
    return monitorForElements({
      onDrop: handleDrop,
    });
  }, [handleDrop]);

  return (
    <>
      <MenuNameForm />
      <hr />
      <div className="menuEditor mb-4">
        <LeftPanel />
        <RightPanel />
        <SectionModal />
      </div>
    </>
  )
}

const container = document.getElementById('menu-editor');

const menu = JSON.parse(container.dataset.menu);

let preloadedState = {
  menu
};

const store = createStoreFromPreloadedState(preloadedState);

createRoot(container).render(
  <Provider store={ store }>
    <MenuEditor
      restaurant={ JSON.parse(container.dataset.restaurant) } />
  </Provider>
)
