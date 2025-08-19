import { ref } from 'vue'
import axios from 'axios'

interface StorageAccount {
  id: number
  provider: string
  provider_display_name: string
  email: string
  name: string
}

interface FileItem {
  id: string
  name: string
  type: 'file' | 'folder'
  mimeType?: string
  size?: number
  thumbnailUrl?: string
  webViewLink?: string
  downloadUrl?: string
  modifiedAt?: string
  iconUrl?: string
  isImage?: boolean
  isVideo?: boolean
  path?: string
}

export function useStorage() {
  const accounts = ref<StorageAccount[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchStorageAccounts = async () => {
    loading.value = true
    error.value = null
    
    try {
      const response = await axios.get('/storage')
      
      if (response.data?.props?.accounts) {
        accounts.value = response.data.props.accounts
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch storage accounts'
      console.error('Storage accounts fetch error:', err)
    } finally {
      loading.value = false
    }
  }

  const getDownloadUrl = async (accountId: number, fileId: string): Promise<string> => {
    try {
      const response = await axios.get(`/storage/accounts/${accountId}/files/${fileId}/download-url`)
      
      if (response.data.success) {
        return response.data.data.url
      } else {
        throw new Error(response.data.error || 'Failed to get download URL')
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.error || 'Failed to get download URL')
    }
  }

  const getShareableUrl = async (accountId: number, fileId: string): Promise<string> => {
    try {
      const response = await axios.get(`/storage/accounts/${accountId}/files/${fileId}/shareable-url`)
      
      if (response.data.success) {
        return response.data.data.url
      } else {
        throw new Error(response.data.error || 'Failed to get shareable URL')
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.error || 'Failed to get shareable URL')
    }
  }

  const insertImageIntoEditor = (
    editor: any,
    file: FileItem,
    downloadUrl: string,
    insertType: 'img' | 'background' | 'url' = 'img'
  ) => {
    if (!editor) return

    const doc = editor.state.doc
    const selection = editor.state.selection
    
    let insertText = ''
    
    switch (insertType) {
      case 'img':
        insertText = `<img src="${downloadUrl}" alt="${file.name}" style="max-width: 100%; height: auto;" />`
        break
      case 'background':
        insertText = `background-image: url('${downloadUrl}');`
        break
      case 'url':
        insertText = downloadUrl
        break
    }
    
    // Insert at cursor position
    const transaction = editor.state.tr.insertText(insertText, selection.from, selection.to)
    editor.dispatch(transaction)
  }

  return {
    accounts,
    loading,
    error,
    fetchStorageAccounts,
    getDownloadUrl,
    getShareableUrl,
    insertImageIntoEditor,
  }
}