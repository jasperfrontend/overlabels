import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

// Get current file path and directory
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Read the JSON file
const jsonData = readFileSync(join(__dirname, './resources/templates/template-tags.json'), 'utf8');
const data = JSON.parse(jsonData);

// Display the structure
console.log('Success:', data.success);
console.log('Tags structure:');
console.log('Tags keys:', Object.keys(data.tags));

// Show the structure of the first category
const firstCategory = Object.keys(data.tags)[0];
console.log(`\nFirst category (${firstCategory}) structure:`);
console.log('Is array:', Array.isArray(data.tags[firstCategory]));
console.log('Type:', typeof data.tags[firstCategory]);

// Display the structure of the first category
if (typeof data.tags[firstCategory] === 'object' && data.tags[firstCategory] !== null) {
  console.log('Keys in first category:', Object.keys(data.tags[firstCategory]));

  // Check the 'tags' property specifically
  if (data.tags[firstCategory].tags) {
    console.log('\nTags array in first category:');
    console.log('Is array:', Array.isArray(data.tags[firstCategory].tags));

    if (Array.isArray(data.tags[firstCategory].tags) && data.tags[firstCategory].tags.length > 0) {
      console.log('Number of tags:', data.tags[firstCategory].tags.length);
      console.log('First tag example:', JSON.stringify(data.tags[firstCategory].tags[0], null, 2));
    }
  }
}
