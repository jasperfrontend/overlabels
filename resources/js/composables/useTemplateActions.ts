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

  const publicUrl = computed(() => {
    return `${window.location.origin}/overlay/${template?.slug}/public`;
  });

  const authUrl = computed(() => {
    return `${window.location.origin}/overlay/${template?.slug}#YOUR_TOKEN_HERE`;
  });

  const previewTemplate = () => {
    const url = template?.is_public ? publicUrl.value : authUrl.value;
    window.open(url, '_blank');
  };

  const forkTemplate = async () => {
    if (!confirm('Fork this template?')) return;

    try {
      const response = await axios.post(route('templates.fork', template));
      const data = response.data;

      if (data.has_controls && data.source_controls?.length > 0) {
        // Show import wizard before navigating
        forkWizardTemplateId.value = data.template.id;
        forkWizardTemplateSlug.value = data.template.slug;
        forkWizardSourceControls.value = data.source_controls;
        forkWizardOpen.value = true;
      } else {
        router.visit(route('templates.show', data.template));
      }
    } catch (error) {
      console.error('Failed to fork template:', error);
      showToast.value = true;
      toastMessage.value = 'Failed to fork template.';
      toastType.value = 'error';
    }
  };

  const deleteTemplate = () => {
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) return;

    router.delete(route('templates.destroy', template), {
      onSuccess: () => {
        // Will redirect to index
      },
    });
  };

  return {
    publicUrl,
    authUrl,
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
  };
}