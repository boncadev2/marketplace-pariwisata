import { useEffect, useState } from 'react';

export function usePublicResource(api, path) {
  const [revision, setRevision] = useState(0);
  const [state, setState] = useState({ key: null, loading: true, result: null, error: null });
  const key = `${path}:${revision}`;
  useEffect(() => {
    if (!api || !path) return;
    const controller = new AbortController();
    setState({ key, loading: true, result: null, error: null });
    api(path, { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) setState({ key, loading: false, result, error: null });
      })
      .catch((error) => {
        if (!controller.signal.aborted) setState({ key, loading: false, result: null, error });
      });
    return () => controller.abort();
  }, [api, path, key]);
  return {
    ...(state.key === key ? state : { loading: true, result: null, error: null }),
    reload: () => setRevision((value) => value + 1),
  };
}
