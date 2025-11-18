import { createContext } from 'react';

export type UserContextType = {
  isDispatcher: boolean;
};

export const UserContext = createContext<UserContextType>({
  isDispatcher: false,
});
