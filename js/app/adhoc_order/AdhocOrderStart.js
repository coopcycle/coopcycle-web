import React, { Component } from "react";
import { withTranslation } from "react-i18next";

import AdhocOrderForm from './AdhocOrderForm'
import SearchOrder from "./SearchOrder";

class AdhocOrderStart extends Component {

  constructor(props) {
    super(props)

    this.state = {
      createNewOrder: false,
      existingOrderLoaded: false,
    }
  }

  render() {
    return (
      <div>
        { this.state.createNewOrder || this.state.existingOrderLoaded
          ?
            <AdhocOrderForm
              onSearchOrderPressed={() => this.setState({createNewOrder: false, existingOrderLoaded: false})}
              existingOrderLoaded={this.state.existingOrderLoaded}>
            </AdhocOrderForm>
          :
            <SearchOrder
              onCreateNewOrderPressed={() => this.setState({createNewOrder: true})}
              onOrderLoaded={() => this.setState({existingOrderLoaded: true})}>
            </SearchOrder>
        }
      </div>
    )
  }
}

export default withTranslation()(AdhocOrderStart)
