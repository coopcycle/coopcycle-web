import { configureStore } from '@reduxjs/toolkit'
import { TypedUseSelectorHook, useDispatch, useSelector } from 'react-redux'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { apiSlice } from '../../../api/slice'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

export const store = configureStore({
  reducer: {
    [accountSlice.name]: accountSlice.reducer,
    [apiSlice.reducerPath]: apiSlice.reducer,
  },
  preloadedState: buildInitialState(),
  middleware: getDefaultMiddleware =>
    getDefaultMiddleware().concat(apiSlice.middleware),
})

// Infer the `RootState` and `AppDispatch` types from the store itself
export type RootState = ReturnType<typeof store.getState>
// Inferred type: {posts: PostsState, comments: CommentsState, users: UsersState}
export type AppDispatch = typeof store.dispatch

// Use throughout your app instead of plain `useDispatch` and `useSelector`
export const useAppDispatch: () => AppDispatch = useDispatch
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector
// TODO; replace with after migrating to react-redux v9.0
// export const useAppDispatch = useDispatch.withTypes<AppDispatch>()
// export const useAppSelector = useSelector.withTypes<RootState>()
