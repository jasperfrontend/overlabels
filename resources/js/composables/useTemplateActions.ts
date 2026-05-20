import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { clearListContext } from '@/composables/useListContext';

interface TemplateActionOptions {
  // Resolves the URL to return to after a successful delete. Provided by the
  // page (show/edit) so the redirect uses the SAME frozen list context the
  // breadcrumb shows - the two can never drift apart. Falls back to the
  // unfiltered templates index when omitted.
  redirectAfterDelete?: () => string;
}

export function useTemplateActions(template: any, options: TemplateActionOptions = {}) {
  const toastMessage = ref('');
  const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
  const showToast = ref(false);

  // Fork Import Wizard state
  const forkWizardOpen = ref(false);
  const forkWizardTemplateId = ref<number>(0);
  const forkWizardTemplateSlug = ref<string>('');
  const forkWizardSourceControls = ref<any[]>([]);
  const forkWizardRequiredServices = ref<string[]>([]);
  const forkWizardConnectedServices = ref<string[]>([]);

  const canDelete = computed(() => !template?.kits_exists);

  const publicUrl = computed(() => {
    return `${window.location.origin}/overlay/${template?.slug}/public`;
  });

  const authUrl = computed(() => {
    return `${window.location.origin}/overlay/${template?.slug}/#YOUR_TOKEN_HERE`;
  });

  const previewTemplate = () => {
    const url = template?.is_public ? publicUrl.value : authUrl.value;
    window.open(url, '_blank');
  };

  const openWizardFromPayload = (data: any): boolean => {
    if (!data?.template?.id) return false;
    const hasControls = data.has_controls && data.source_controls?.length > 0;
    const hasRequiredServices = data.required_services?.length > 0;
    if (!hasControls && !hasRequiredServices) return false;

    forkWizardTemplateId.value = data.template.id;
    forkWizardTemplateSlug.value = data.template.slug;
    forkWizardSourceControls.value = data.source_controls ?? [];
    forkWizardRequiredServices.value = data.required_services ?? [];
    forkWizardConnectedServices.value = data.connected_services ?? [];
    forkWizardOpen.value = true;
    return true;
  };

  const forkTemplate = async () => {
    if (!confirm('Copy this template?')) return;

    try {
      const response = await axios.post(route('templates.fork', template));
      const data = response.data;

      if (!openWizardFromPayload(data)) {
        router.visit(route('templates.show', data.template));
      }
    } catch (error) {
      console.error('Failed to copy template:', error);
      showToast.value = true;
      toastMessage.value = 'Failed to copy template.';
      toastType.value = 'error';
    }
  };

  const deleteTemplate = async () => {
    if (!canDelete.value) return;
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) return;

    // Use plain axios (not router.delete) so we don't follow the controller's
    // Inertia redirect to /templates - we want to return the user to the
    // filtered list they came from. The target is the same frozen list context
    // the breadcrumb renders (see useListContext), so the redirect can't drift
    // from what the breadcrumb showed.
    try {
      await axios.delete(route('templates.destroy', template), {
        headers: { Accept: 'application/json' },
      });

      const target = options.redirectAfterDelete?.() ?? route('templates.index');
      clearListContext(template?.id);
      router.visit(target);
    } catch (error: any) {
      const msg = error?.response?.data?.error ?? 'Failed to delete template.';
      showToast.value = false;
      toastMessage.value = msg;
      toastType.value = 'error';
      showToast.value = true;
    }
  };

  return {
    publicUrl,
    authUrl,
    canDelete,
    previewTemplate,
    forkTemplate,
    deleteTemplate,
    toastMessage,
    toastType,
    showToast,
    forkWizardOpen,
    forkWizardTemplateId,
    forkWizardTemplateSlug,
    forkWizardSourceControls,
    forkWizardRequiredServices,
    forkWizardConnectedServices,
    openWizardFromPayload,
  };
}
