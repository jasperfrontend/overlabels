import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

// Get current file path and directory
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Read the JSON file
const jsonData = readFileSync(join(__dirname, './resources/templates/template-tags.json'), 'utf8');
const response = { data: JSON.parse(jsonData) };

// Simulate the fixed code from create.vue
const tags = response.data.tags;
const tagList = [];

// Flatten the categorized tags (fixed version)
Object.entries(tags).forEach(([category, categoryData]) => {
  if (categoryData.tags && Array.isArray(categoryData.tags)) {
    categoryData.tags.forEach(tag => {
      tagList.push(`${tag.display_tag} - ${tag.description}`);
    });
  }
});

// Display results
console.log('Number of tags processed:', tagList.length);
console.log('First 5 tags:');
tagList.slice(0, 5).forEach(tag => console.log(' -', tag));

// Test the original buggy code for comparison
const buggyTagList = [];
Object.entries(tags).forEach(([categoryTags]) => {
  try {
    categoryTags.forEach(tag => {
      buggyTagList.push(`${tag.tag} - ${tag.description}`);
    });
  } catch (error) {
    console.log('\nError with buggy code:', error.message);
  }
});
