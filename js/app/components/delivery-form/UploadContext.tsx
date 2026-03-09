import { createContext } from 'react';
import type { UploadContextType } from './types';

const UploadContext = createContext<UploadContextType>({
  endpoint: '',
});

export default UploadContext;
