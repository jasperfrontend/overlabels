<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Codemirror } from 'vue-codemirror'
import { html } from '@codemirror/lang-html'
import { css } from '@codemirror/lang-css'
import { oneDark } from '@codemirror/theme-one-dark'
import { EditorView } from '@codemirror/view'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Eye, Save, Code, Palette, Play, AlertCircle, CheckCircle } from 'lucide-vue-next'

interface Props {
  overlayHash: {
    hash_key: string
    overlay_name: string
    slug: string
    id: number
  }
  existingTemplate: {
    html: string
    css: string
  }
  availableTags: Array<{
    tag_name: string
    display_name: string
    description: string
    data_type: string
    category: string
    sample_data?: string
  }>
}

const props = defineProps<Props>()

// State
const htmlTemplate = ref(props.existingTemplate.html || `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overlay Template</title>
</head>
<body>
    <div class="overlay-container">
        <h1>[[[channel_name]]]</h1>
        <div class="stats">
            <div class="follower-count">
                Followers: [[[followers_total]]]
            </div>
            <div class="latest-follower">
                Latest: [[[followers_latest_name]]]
            </div>
        </div>
    </div>
</body>
</html>`)

const cssTemplate = ref(props.existingTemplate.css || `body {
    font-family: 'Arial', sans-serif;
    background: transparent;
    color: white;
    margin: 0;
    padding: 20px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
}

.overlay-container {
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 30px;
    max-width: 400px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stats {
    margin-top: 20px;
}

.follower-count, .latest-follower {
    font-size: 18px;
    margin: 10px 0;
    padding: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
}`)

const showPreview = ref(true)
const isSaving = ref(false)
const saveMessage = ref('')
const validationResults = ref<any>(null)
const isValidating = ref(false)

// Theme detection
const isDark = ref(document.documentElement.classList.contains('dark'))

// CodeMirror extensions
const htmlExtensions = computed(() => [
  html(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' }
  }),
  ...(isDark.value ? [oneDark] : [])
])

const cssExtensions = computed(() => [
  css(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' }
  }),
  ...(isDark.value ? [oneDark] : [])
])

// Computed preview HTML
const previewHtml = computed(() => {
  let html = htmlTemplate.value
  
  if (cssTemplate.value.trim()) {
    if (html.includes('<style>')) {
      html = html.replace(/<style[^>]*>[\s\S]*?<\/style>/g, `<style>${cssTemplate.value}</style>`)
    } else {
      html = html.replace('</head>', `<style>${cssTemplate.value}</style>\n</head>`)
    }
  }
  
  return html
})

// Grouped template tags by category
const tagsByCategory = computed(() => {
  const grouped: Record<string, typeof props.availableTags> = {}
  
  props.availableTags.forEach(tag => {
    if (!grouped[tag.category]) {
      grouped[tag.category] = []
    }
    grouped[tag.category].push(tag)
  })
  
  return grouped
})

// Methods
const insertTag = (tagName: string) => {
  const tag = `[[[${tagName}]]]`
  htmlTemplate.value += tag
}

const validateTemplate = async () => {
  isValidating.value = true
  validationResults.value = null
  
  try {
    const response = await fetch(route('api.template.validate'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        html_template: htmlTemplate.value,
        css_template: cssTemplate.value
      })
    })
    
    validationResults.value = await response.json()
  } catch (error) {
    console.error('Validation error:', error)
    validationResults.value = {
      success: false,
      errors: ['Failed to validate template']
    }
  } finally {
    isValidating.value = false
  }
}

const saveTemplate = async () => {
  isSaving.value = true
  saveMessage.value = ''
  
  try {
    const response = await fetch(route('api.template.save'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        overlay_slug: props.overlayHash.slug, // Now uses slug for safety!
        html_template: htmlTemplate.value,
        css_template: cssTemplate.value
      })
    })
    
    const result = await response.json()
    
    if (result.success) {
      saveMessage.value = 'Template saved successfully!'
      setTimeout(() => saveMessage.value = '', 3000)
    } else {
      saveMessage.value = result.message || 'Failed to save template'
    }
  } catch (error) {
    console.error('Save error:', error)
    saveMessage.value = 'Failed to save template'
  } finally {
    isSaving.value = false
  }
}

const previewOverlay = () => {
  const url = `/overlay/${props.overlayHash.slug}/${props.overlayHash.hash_key}`
  window.open(url, '_blank')
}

// Watch for theme changes
onMounted(() => {
  const observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark')
  })
  
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  })
})
</script>

<template>
  <div class="space-y-6">
    <!-- Control Bar -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>{{ overlayHash.overlay_name }}</CardTitle>
            <p class="text-sm text-muted-foreground mt-1">
              Editing template for overlay: {{ overlayHash.slug }}
            </p>
          </div>
          <div class="flex gap-2">
            <Button 
              @click="validateTemplate" 
              :disabled="isValidating"
              variant="outline"
              size="sm"
            >
              <AlertCircle class="w-4 h-4 mr-2" />
              {{ isValidating ? 'Validating...' : 'Validate' }}
            </Button>
            <Button 
              @click="previewOverlay" 
              variant="outline"
              size="sm"
            >
              <Eye class="w-4 h-4 mr-2" />
              Preview Live
            </Button>
            <Button 
              @click="saveTemplate" 
              :disabled="isSaving"
              size="sm"
            >
              <Save class="w-4 h-4 mr-2" />
              {{ isSaving ? 'Saving...' : 'Save Template' }}
            </Button>
          </div>
        </div>
      </CardHeader>
    </Card>

    <!-- Success/Error Messages -->
    <div v-if="saveMessage" 
         :class="saveMessage.includes('success') ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
         class="border rounded-lg p-4">
      {{ saveMessage }}
    </div>

    <!-- Validation Results -->
    <Card v-if="validationResults" class="border-yellow-200">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <component :is="validationResults.success ? CheckCircle : AlertCircle" 
                     :class="validationResults.success ? 'text-green-600' : 'text-red-600'" 
                     class="w-5 h-5" />
          Template Validation
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="validationResults.errors?.length" class="mb-4">
          <h4 class="font-medium text-red-800 mb-2">Errors:</h4>
          <ul class="list-disc list-inside text-red-700 space-y-1">
            <li v-for="error in validationResults.errors" :key="error">{{ error }}</li>
          </ul>
        </div>
        <div v-if="validationResults.warnings?.length">
          <h4 class="font-medium text-yellow-800 mb-2">Warnings:</h4>
          <ul class="list-disc list-inside text-yellow-700 space-y-1">
            <li v-for="warning in validationResults.warnings" :key="warning">{{ warning }}</li>
          </ul>
        </div>
      </CardContent>
    </Card>

    <!-- Main Editor Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Template Tags Sidebar -->
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle class="text-base">Template Tags</CardTitle>
          <p class="text-sm text-muted-foreground">
            Click to insert into HTML
          </p>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-for="(tags, category) in tagsByCategory" :key="category">
            <h4 class="font-medium text-sm mb-2">{{ category }}</h4>
            <div class="space-y-1">
              <Button
                v-for="tag in tags"
                :key="tag.tag_name"
                @click="insertTag(tag.tag_name)"
                variant="ghost"
                size="sm"
                class="w-full justify-start text-xs h-auto py-1"
                :title="tag.description"
              >
                <code class="text-xs">[[[{{ tag.tag_name }}]]]</code>
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Editor Area -->
      <div class="lg:col-span-3 space-y-6">
        <Tabs default-value="html" class="w-full">
          <TabsList class="grid w-full grid-cols-3">
            <TabsTrigger value="html" class="flex items-center gap-2">
              <Code class="w-4 h-4" />
              HTML
            </TabsTrigger>
            <TabsTrigger value="css" class="flex items-center gap-2">
              <Palette class="w-4 h-4" />
              CSS
            </TabsTrigger>
            <TabsTrigger value="preview" class="flex items-center gap-2">
              <Eye class="w-4 h-4" />
              Preview
            </TabsTrigger>
          </TabsList>

          <TabsContent value="html" class="mt-4">
            <Card>
              <CardHeader>
                <CardTitle class="text-base">HTML Template</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="border rounded-lg overflow-hidden">
                  <Codemirror
                    v-model="htmlTemplate"
                    :style="{ height: '500px' }"
                    :autofocus="true"
                    :indent-with-tab="true"
                    :tab-size="2"
                    :extensions="htmlExtensions"
                    placeholder="Enter your HTML template here... Use [[[tag_name]]] for dynamic content"
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="css" class="mt-4">
            <Card>
              <CardHeader>
                <CardTitle class="text-base">CSS Styles</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="border rounded-lg overflow-hidden">
                  <Codemirror
                    v-model="cssTemplate"
                    :style="{ height: '500px' }"
                    :indent-with-tab="true"
                    :tab-size="2"
                    :extensions="cssExtensions"
                    placeholder="Enter your CSS styles here..."
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="preview" class="mt-4">
            <Card>
              <CardHeader>
                <CardTitle class="text-base">Live Preview</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="border rounded-lg bg-gray-50 dark:bg-gray-900">
                  <iframe
                    :srcdoc="previewHtml"
                    class="w-full h-[500px] border-0"
                    sandbox="allow-same-origin"
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.cm-editor) {
  height: 100%;
}

:deep(.cm-scroller) {
  font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', 'Consolas', monospace;
}
</style>