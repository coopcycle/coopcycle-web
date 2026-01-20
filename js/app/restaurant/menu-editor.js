import React, { useEffect, useState, useRef, useCallback } from 'react';
import { createRoot } from 'react-dom/client'
// import {
//   DndContext,
//   useDroppable,
//   useDraggable,
//   closestCenter,
//   closestCorners,
//   KeyboardSensor,
//   PointerSensor,
//   MouseSensor,
//   useSensor,
//   useSensors,
// } from '@dnd-kit/core';
// import {
//   arrayMove,
//   SortableContext,
//   useSortable,
//   sortableKeyboardCoordinates,
//   verticalListSortingStrategy,
// } from '@dnd-kit/sortable'
// import { CSS } from '@dnd-kit/utilities';

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
import { fetchProducts, removeProductFromSection, addProductToSection, setSectionProducts } from './menu-editor/actions'
import { selectProducts, selectMenuSections } from './menu-editor/selectors'

import './menu-editor.scss'

const httpClient = new window._auth.httpClient()

const Section = ({ section, index }) => {

  const ref = useRef(null); // Create a ref for the column
  const [ isDraggedOver, setIsDraggedOver ] = useState(false);

  useEffect(() => {
    const el = ref.current;

    // Set up the drop target for the column element
    return dropTargetForElements({
      element: el,
      onDragStart: () => setIsDraggedOver(true),
      onDragEnter: () => setIsDraggedOver(true),
      onDragLeave: () => setIsDraggedOver(false),
      onDrop: () => setIsDraggedOver(false),
      getData: () => ({ sectionId: section['@id'] }),
      getIsSticky: () => true,
    });
  }, [section['@id']]);

  return (
    <div className="menuEditor__panel mb-4">
      <h4 className="menuEditor__panel__title">
        <i className="fa fa-arrows mr-2" aria-hidden="true"></i>
        <a href="#">
          <span className="mr-2">{ section.name }</span>
          <i className="fa fa-pencil" aria-hidden="true"></i>
        </a>
        <a className="pull-right" href="#">
          <i className="fa fa-close"></i>
        </a>
      </h4>
      <div className={`menuEditor__panel__body ${isDraggedOver ? "menuEditor__panel__body--dragged" : ""}`} ref={ref}>
        { section.hasMenuItem.map((product) => (
          <Product key={ product['@id'] } product={ product } />
        )) }
      </div>
    </div>
  )
}

const LeftPanel = () => {

  const sections = useSelector(selectMenuSections)

  return (
    <div className="menuEditor__left">
      { sections.map((section, index) => (
        <Section key={`section-${index}`} section={section} index={ index } />
      ))}
      <div className="d-flex flex-row align-items-center justify-content-between border p-4">
        <strong>Add child</strong>
        <button type="button" className="btn btn-success" data-toggle="modal" data-target="#newChildTaxonModal">
          <i className="fa fa-plus mr-2"></i><span>Add</span>
        </button>
      </div>
    </div>
  )
}

const RightPanel = () => {

  // const { isOver, setNodeRef } = useDroppable({
  //   id: 'products',
  // });
  const products = useSelector(selectProducts)

  // console.log('isOver', isOver)

  return (
    <div className="menuEditor__right">
      <div className="menuEditor__panel menuEditor__productList">
        <h4 className="menuEditor__panel__title">
          Products {/*{{ 'form.menu_editor.products_panel.title'|trans }}*/}
        </h4>
        <div className="menuEditor__panel__body">
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
      // Add dropTargetForElements to make the card a drop target
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
        // onDragEnter: (args) => {
        //   if (args.source.data.productId !== product['@id']) {
        //     console.log("onDragEnter", args);
        //   }
        // },
      })
    );
    // Update the dependency array
  }, [ product['@id'] ]);

  return (
    <div className="menuEditor__product" ref={ref}>
      { product.name }
    </div>
  )
}

const MenuEditor = ({ restaurant }) => {

  // const [ menu, setMenu ] = useState(defaultMenu)

  const dispatch = useDispatch()

  // const sensors = useSensors(
  //   useSensor(PointerSensor),
  //   useSensor(MouseSensor),
  //   useSensor(KeyboardSensor, {
  //     coordinateGetter: sortableKeyboardCoordinates,
  //   })
  // );

  useEffect(() => {
    dispatch(fetchProducts(restaurant));
  }, [dispatch])

  const sections = useSelector(selectMenuSections)

  const reorderProduct = useCallback(
    ({ sectionId, startIndex, finishIndex }) => {

      // console.log('reorderProduct', sectionId)

      // Get the source column data
      const sourceSectionData = _.find(sections, (s) => s['@id'] === sectionId);

      // Call the reorder function to get a new array
      // of cards with the moved card's new position
      const updatedItems = reorder({
        list: sourceSectionData.hasMenuItem,
        startIndex,
        finishIndex,
      });

      console.log('updatedItems', updatedItems)

      // Create a new object for the source column
      // with the updated list of cards
      // const updatedSourceColumn = {
      //   ...sourceColumnData,
      //   cards: updatedItems,
      // };

      // Update columns state
      // setColumnsData({
      //   ...columnsData,
      //   [columnId]: updatedSourceColumn,
      // });

      dispatch(setSectionProducts(sectionId, updatedItems))
    },
    [sections]
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
      const sourceSectionData = _.find(sections, (s) => s['@id'] === sourceSectionId); // columnsData[sourceSectionId];

      // Get the index of the card being dragged in the source column
      const draggedProductIndex = sourceSectionData.hasMenuItem.findIndex(
        (product) => product['@id'] === draggedProductId
      );

      // Reordering within a column by dropping in an empty space
      if (location.current.dropTargets.length === 1) {

        console.log('reorder')

        // Get the destination column from the current drop targets
        const [destinationSectionRecord] = location.current.dropTargets;

        // Retrieve the ID of the destination column
        const destinationSectionId = destinationSectionRecord.data.sectionId;

        // check if the source and destination columns are the same
        if (sourceSectionId === destinationSectionId) {

          // Calculate the destination index for the dragged card within the same column
          const destinationIndex = getReorderDestinationIndex({
            startIndex: draggedProductIndex,
            indexOfTarget: sourceSectionData.hasMenuItem.length - 1,
            closestEdgeOfTarget: null,
            axis: "vertical",
          });

          // will implement this function
          reorderProduct({
            sectionId: sourceSectionData.sectionId,
            startIndex: draggedProductIndex,
            finishIndex: destinationIndex,
          });

          return;
        }
      }

      if (location.current.dropTargets.length === 2) {
        // Destructure and extract the destination card and column data from the drop targets
        const [destinationProductRecord, destinationSectionRecord] =
          location.current.dropTargets;

        // Extract the destination column ID from the destination column data
        const destinationSectionId = destinationSectionRecord.data.sectionId;

        // Retrieve the destination column data using the destination column ID
        const destinationSection = _.find(sections, (s) => s['@id'] === destinationSectionId);

        // Find the index of the target card within the destination column's cards
        const indexOfTarget = destinationSection.hasMenuItem.findIndex(
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
      }
    }
  }, [ sections ]); // TODO Add sections to dependencies array

  // setup the monitor
  useEffect(() => {
    return monitorForElements({
      onDrop: handleDrop,
    });
  }, [handleDrop]);

  return (
    // <DndContext
    //   onDragOver={handleDragOver}
    //   onDragEnd={handleDragEnd}
    //   sensors={sensors}
    //   collisionDetection={ /*closestCenter*/ closestCorners}>
      <div className="menuEditor mb-4">
        {/* TODO Add form input for menu name */}
        <LeftPanel />
        <RightPanel />
      </div>
    // </DndContext>
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
      restaurant={ JSON.parse(container.dataset.restaurant) }
      defaultMenu={ JSON.parse(container.dataset.menu) } />
  </Provider>
)
