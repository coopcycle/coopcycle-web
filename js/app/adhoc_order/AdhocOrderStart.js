import React, { Component } from "react";
import { withTranslation } from "react-i18next";

import AdhocOrderForm from './AdhocOrderForm'
import SearchOrder from "./SearchOrder";

class AdhocOrderStart extends Component {

  constructor(props) {
    super(props)

    this.state = {
      createNewOrder: false,
      exstingOrderLoaded: false,
    }
  }

  render() {
    return (
      <div>
        { this.state.createNewOrder || this.state.exstingOrderLoaded
          ?
            <AdhocOrderForm
              onSearchOrderPressed={() => this.setState({createNewOrder: false, exstingOrderLoaded: false})}
              exstingOrderLoaded={this.state.exstingOrderLoaded}>
            </AdhocOrderForm>
          :
            <SearchOrder
              onCreateNewOrderPressed={() => this.setState({createNewOrder: true})}
              onOrderLoaded={() => this.setState({exstingOrderLoaded: true})}>
            </SearchOrder>
        }
      </div>
    )
  }
}

export default withTranslation()(AdhocOrderStart)
