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

// State - Now uses the beautiful default from backend
const htmlTemplate = ref(props.existingTemplate.html || getDefaultHtmlTemplate())
const cssTemplate = ref(props.existingTemplate.css || getDefaultCssTemplate())

const showPreview = ref(true)
const isSaving = ref(false)
const saveMessage = ref('')
const validationResults = ref<any>(null)
const isValidating = ref(false)

// Theme detection
const isDark = ref(document.documentElement.classList.contains('dark'))

// Default templates that match the backend's returnDefaultHtmlOverlay
function getDefaultHtmlTemplate(): string {
  return String.raw`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[[[overlay_name]]]</title>
</head>
<body>
    <div class="overlay-container">
        <div class="overlay-title">[[[overlay_name]]]</div>
        
        <div class="stat-row">
            <span class="stat-label">Channel:</span>
            <span class="stat-value">[[[channel_name]]]</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Followers:</span>
            <span class="stat-value">[[[followers_total]]]</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Latest Follower:</span>
            <span class="stat-value">[[[followers_latest_name]]]</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Subscribers:</span>
            <span class="stat-value">[[[subscribers_total]]]</span>
        </div>
        
        <div class="timestamp">
            Last updated: [[[timestamp]]]
        </div>
        
        <div class="setup-note">
            ✨ This is a default overlay! Create a custom template in your dashboard to personalize this.
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds for live data
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    <\/script>
</body>
</html>`
}

function getDefaultCssTemplate(): string {
  return String.raw`* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: transparent;
    color: white;
    overflow: hidden;
}
.overlay-container {
    padding: 20px;
    background: linear-gradient(135deg, rgba(139, 69, 19, 0.9) 0%, rgba(30, 30, 60, 0.9) 100%);
    border-radius: 15px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    margin: 20px;
}
.overlay-title {
    font-size: 1.5em;
    font-weight: bold;
    margin-bottom: 15px;
    text-align: center;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}
.stat-row {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.stat-label {
    font-weight: 500;
    opacity: 0.8;
}
.stat-value {
    font-weight: bold;
    color: #FFD700;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}
.timestamp {
    text-align: center;
    font-size: 0.8em;
    opacity: 0.6;
    margin-top: 15px;
}
.setup-note {
    background: rgba(255, 255, 255, 0.1);
    padding: 10px;
    border-radius: 8px;
    margin-top: 15px;
    font-size: 0.85em;
    text-align: center;
    border-left: 4px solid #FFD700;
}`
}

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
      setTimeout(() => {
        saveMessage.value = ''
      }, 3000)
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

const resetToDefault = () => {
  if (confirm('Are you sure you want to reset to the default template? This will overwrite your current changes.')) {
    htmlTemplate.value = getDefaultHtmlTemplate()
    cssTemplate.value = getDefaultCssTemplate()
    saveMessage.value = 'Reset to default template!'
    setTimeout(() => {
      saveMessage.value = ''
    }, 3000)
  }
}

const previewLive = () => {
  const url = `/overlay/${props.overlayHash.slug}/${props.overlayHash.hash_key}`
  window.open(url, '_blank')
}

// Watch for theme changes
watch(() => document.documentElement.classList.contains('dark'), (newDark) => {
  isDark.value = newDark
})
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Left sidebar with template tags and actions -->
    <div class="lg:col-span-1 space-y-6">
      <!-- Action buttons -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base flex items-center gap-2">
            <Play class="w-4 h-4" />
            Actions
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <Button @click="validateTemplate" :disabled="isValidating" class="w-full">
            <AlertCircle v-if="isValidating" class="w-4 h-4 mr-2 animate-spin" />
            <CheckCircle v-else class="w-4 h-4 mr-2" />
            {{ isValidating ? 'Validating...' : 'Validate Template' }}
          </Button>
          
          <Button @click="saveTemplate" :disabled="isSaving" class="w-full" variant="default">
            <Save class="w-4 h-4 mr-2" />
            {{ isSaving ? 'Saving...' : 'Save Template' }}
          </Button>

          <Button @click="previewLive" class="w-full" variant="secondary">
            <Eye class="w-4 h-4 mr-2" />
            Preview Live
          </Button>

          
          <Button @click="resetToDefault" class="w-full" variant="outline">
            Reset to Default
          </Button>
          
          <!-- Save message -->
          <div v-if="saveMessage" class="text-sm text-center p-2 rounded-md" 
               :class="saveMessage.includes('success') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'">
            {{ saveMessage }}
          </div>
        </CardContent>
      </Card>

      <!-- Template validation results -->
      <Card v-if="validationResults">
        <CardHeader>
          <CardTitle class="text-base flex items-center gap-2">
            <AlertCircle class="w-4 h-4" />
            Validation Results
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="validationResults.success" class="text-green-600 dark:text-green-400 text-sm">
            ✅ Template is valid!
          </div>
          <div v-else class="space-y-2">
            <div v-if="validationResults.errors?.length" class="text-red-600 dark:text-red-400 text-sm">
              <strong>Errors:</strong>
              <ul class="list-disc list-inside">
                <li v-for="error in validationResults.errors" :key="error">{{ error }}</li>
              </ul>
            </div>
            <div v-if="validationResults.warnings?.length" class="text-yellow-600 dark:text-yellow-400 text-sm">
              <strong>Warnings:</strong>
              <ul class="list-disc list-inside">
                <li v-for="warning in validationResults.warnings" :key="warning">{{ warning }}</li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Available template tags -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Template Tags</CardTitle>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Click to insert template tags into your HTML
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
    </div>

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
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Use template tags like <code>[[[channel_name]]]</code> for dynamic content
              </p>
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
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Style your overlay with CSS. Use transparent backgrounds for OBS compatibility.
              </p>
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
              <p class="text-sm text-gray-600 dark:text-gray-400">
                This preview shows how your overlay will look with sample data
              </p>
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
</template>

<style scoped>
:deep(.cm-editor) {
  height: 100%;
}

:deep(.cm-scroller) {
  font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', 'Consolas', monospace;
}
</style>