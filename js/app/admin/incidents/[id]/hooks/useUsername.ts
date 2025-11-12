import { Uri } from '../../../../api/types';
import { useGetUserQuery } from '../../../../api/slice';

export function useUsername(createdBy?: Uri): string | null {
  const { data: user, isLoading } = useGetUserQuery(createdBy || '', {
    skip: !createdBy,
  });

  if (!createdBy || isLoading || !user) {
    return null;
  }

  return user.username;
}
