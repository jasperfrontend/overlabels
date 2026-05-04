import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export function useTemplateActions(template: any) {
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
    // Inertia redirect to /templates - we want to honor the
    // templates_list_context sessionStorage entry that the index page sets,
    // so deleting from show/edit returns the user to the filtered list they
    // came from instead of resetting to the unfiltered index.
    try {
      await axios.delete(route('templates.destroy', template), {
        headers: { Accept: 'application/json' },
      });

      let target = route('templates.index');
      try {
        const stored = sessionStorage.getItem('templates_list_context');
        if (stored) {
          const ctx = JSON.parse(stored);
          if (typeof ctx?.href === 'string') target = ctx.href;
        }
      } catch {
        // fall through to default
      }
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
