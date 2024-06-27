import { organizationAdapter } from "../../coopcycle-frontend-js/logistics/redux"
import { loadOrganizationsSuccess } from "./actions"

const initialState = organizationAdapter.getInitialState()

export default (state = initialState, action) => {
    switch (action.type) {
        case loadOrganizationsSuccess.type:
            return organizationAdapter.upsertMany(organizationAdapter.getInitialState(), action.payload)
    }

    return state
}
