import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export function useTwitchAuth() {
  const isAuthenticated = ref(true);
  const authError = ref<string | null>(null);
  const isRefreshingToken = ref(false);

  const handleAuthError = (error: any) => {
    console.error('Twitch Auth Error:', error);

    // Check if it's a 401 or explicit auth error
    if (
      error?.status === 401 ||
      error?.response?.status === 401 ||
      error?.response?.requires_reauth ||
      error?.message?.includes('Invalid OAuth token')
    ) {
      isAuthenticated.value = false;
      authError.value = 'Your Twitch session has expired. Please re-authenticate.';

      // Show notification to user
      const shouldRedirect = confirm(
        'Your Twitch session has expired. Would you like to re-authenticate now?'
      );

      if (shouldRedirect) {
        window.location.href = '/auth/redirect/twitch';
      }

      return true; // Auth error handled
    }

    return false; // Not an auth error
  };

  const refreshToken = async () => {
    if (isRefreshingToken.value) return;

    isRefreshingToken.value = true;

    try {
      // Attempt to refresh the page data which will trigger backend token refresh
      router.reload({
        //@ts-ignore
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          isAuthenticated.value = true;
          authError.value = null;
        },
        onError: (errors) => {
          handleAuthError(errors);
        }
      });
    } finally {
      isRefreshingToken.value = false;
    }
  };

  const makeAuthenticatedRequest = async (
    url: string,
    options: any = {},
    onSuccess?: () => void,
    onError?: (error: any) => void
  ) => {
    return router.visit(url, {
      ...options,
      onError: (errors) => {
        // First, check if it's an auth error
        const isAuthError = handleAuthError(errors);

        // If not an auth error and there's a custom error handler, call it
        if (!isAuthError && onError) {
          onError(errors);
        }
      },
      onSuccess: () => {
        // Reset auth state on successful request
        isAuthenticated.value = true;
        authError.value = null;

        if (onSuccess) {
          onSuccess();
        }
      }
    });
  };

  return {
    isAuthenticated,
    authError,
    isRefreshingToken,
    handleAuthError,
    refreshToken,
    makeAuthenticatedRequest
  };
}
