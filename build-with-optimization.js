#!/usr/bin/env node
/**
 * Enhanced Build script for Page Duplicator WordPress plugin
 * 
 * This script creates a distribution-ready ZIP file of the plugin
 * with the correct WordPress directory structure and optimized assets.
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { execSync } = require('child_process');

// Plugin info
const pluginSlug = 'page-duplicator';
const pluginVersion = getPluginVersion();
const distDir = './dist';
const tempDir = `${distDir}/temp-build`;
const pluginTempDir = `${tempDir}/${pluginSlug}`;
const isDev = process.argv.includes('--dev');

/**
 * Get the current plugin version from the main plugin file
 * @returns {string} The plugin version
 */
function getPluginVersion() {
  const pluginFile = fs.readFileSync('./page-duplicator.php', 'utf8');
  const versionMatch = pluginFile.match(/Version:\s*([0-9.]+)/i);
  return versionMatch ? versionMatch[1] : '1.0.0';
}

/**
 * Clean build directories
 */
function cleanDirectories() {
  console.log('Cleaning build directories...');
  
  // Remove temp directory if it exists
  if (fs.existsSync(tempDir)) {
    fs.rmSync(tempDir, { recursive: true, force: true });
  }
  
  // Create dist and temp directories
  if (!fs.existsSync(distDir)) {
    fs.mkdirSync(distDir);
  }
  
  fs.mkdirSync(tempDir);
  fs.mkdirSync(pluginTempDir);
}

/**
 * Copy plugin files to temp directory with correct structure
 */
function copyFiles() {
  console.log('Copying plugin files...');
  
  const filesToInclude = [
    'page-duplicator.php',
    'index.php',
    'README.txt',
    'assets',
    'includes',
    'templates',
    'acf-json',
    'languages'
  ];
  
  const excludePatterns = [
    '.git',
    '.github',
    'node_modules',
    '.gitignore',
    '.DS_Store',
    'build.js',
    'build-with-optimization.js',
    'package.json',
    'package-lock.json',
    '.vscode',
    'dist',
    'tests',
    '*.map',
    '*.log',
    '.eslintrc.json',
    '.npmrc'
  ];
  
  // Helper function to check if file should be excluded
  function shouldExclude(filePath) {
    const relativePath = path.relative('./', filePath);
    return excludePatterns.some(pattern => {
      if (pattern.startsWith('*.')) {
        // Handle file extension pattern
        const ext = pattern.replace('*.', '.');
        return filePath.endsWith(ext);
      }
      return relativePath.includes(pattern);
    });
  }
  
  // Helper function to copy directory recursively
  function copyDirectory(source, destination) {
    if (!fs.existsSync(destination)) {
      fs.mkdirSync(destination, { recursive: true });
    }
    
    const entries = fs.readdirSync(source, { withFileTypes: true });
    
    for (const entry of entries) {
      const sourcePath = path.join(source, entry.name);
      const destPath = path.join(destination, entry.name);
      
      // Skip excluded paths
      if (shouldExclude(sourcePath)) {
        continue;
      }
      
      if (entry.isDirectory()) {
        copyDirectory(sourcePath, destPath);
      } else {
        fs.copyFileSync(sourcePath, destPath);
      }
    }
  }
  
  // Copy each file/directory to the plugin's directory
  filesToInclude.forEach(item => {
    try {
      const sourcePath = path.join('./', item);
      const destPath = path.join(pluginTempDir, item);
      
      if (fs.existsSync(sourcePath)) {
        if (fs.statSync(sourcePath).isDirectory()) {
          copyDirectory(sourcePath, destPath);
          console.log(`✓ Copied directory: ${item}`);
        } else {
          fs.copyFileSync(sourcePath, destPath);
          console.log(`✓ Copied file: ${item}`);
        }
      } else {
        console.log(`⚠ Skipped (not found): ${item}`);
      }
    } catch (err) {
      console.error(`Error copying ${item}: ${err.message}`);
    }
  });
}

/**
 * Optimize assets (JS and CSS files)
 */
function optimizeAssets() {
  if (isDev) {
    console.log('Skipping asset optimization in dev mode');
    return;
  }
  
  console.log('Optimizing assets...');
  
  // Optimize JS files
  const jsDir = path.join(pluginTempDir, 'assets/js');
  if (fs.existsSync(jsDir)) {
    fs.readdirSync(jsDir).forEach(file => {
      if (file.endsWith('.js') && !file.endsWith('.min.js')) {
        const filePath = path.join(jsDir, file);
        try {
          console.log(`Minifying JS: ${file}`);
          execSync(`npx terser ${filePath} --compress --mangle --output ${filePath}`);
        } catch (err) {
          console.error(`Error minifying ${file}: ${err.message}`);
        }
      }
    });
  }
  
  // Optimize CSS files
  const cssDir = path.join(pluginTempDir, 'assets/css');
  if (fs.existsSync(cssDir)) {
    fs.readdirSync(cssDir).forEach(file => {
      if (file.endsWith('.css') && !file.endsWith('.min.css')) {
        const filePath = path.join(cssDir, file);
        try {
          console.log(`Minifying CSS: ${file}`);
          execSync(`npx cleancss -o ${filePath} ${filePath}`);
        } catch (err) {
          console.error(`Error minifying ${file}: ${err.message}`);
        }
      }
    });
  }
}

/**
 * Create ZIP file with proper structure
 */
function createZipFile() {
  console.log(`Creating ZIP file for ${pluginSlug} v${pluginVersion}...`);
  
  const zipFileName = isDev 
    ? `${pluginSlug}-dev.zip` 
    : `${pluginSlug}-${pluginVersion}.zip`;
  
  const zipFilePath = path.join(distDir, zipFileName);
  const output = fs.createWriteStream(zipFilePath);
  const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
  });
  
  output.on('close', () => {
    const fileSizeInMB = (archive.pointer() / 1024 / 1024).toFixed(2);
    console.log(`✅ ZIP file created: ${zipFilePath} (${fileSizeInMB} MB)`);
    
    // Clean up
    fs.rmSync(tempDir, { recursive: true, force: true });
  });
  
  archive.on('error', (err) => {
    throw err;
  });
  
  archive.pipe(output);
  
  // Add the plugin directory with the correct name
  archive.directory(pluginTempDir, pluginSlug);
  
  archive.finalize();
}

/**
 * Validate essential files
 */
function validateFiles() {
  console.log('Validating essential files...');
  
  const essentialFiles = [
    'page-duplicator.php',
    'index.php',
    'includes/class-page-duplicator-process.php',
    'includes/class-page-duplicator-logger.php'
  ];
  
  let allFilesExist = true;
  
  essentialFiles.forEach(file => {
    const filePath = path.join(pluginTempDir, file);
    if (!fs.existsSync(filePath)) {
      console.error(`❌ Missing essential file: ${file}`);
      allFilesExist = false;
    } else {
      console.log(`✓ Essential file present: ${file}`);
    }
  });
  
  if (!allFilesExist) {
    throw new Error('Missing essential files for WordPress plugin functionality');
  }
}

/**
 * Main build process
 */
async function build() {
  console.log(`🔄 Starting build process for ${pluginSlug} v${pluginVersion}${isDev ? ' (Development Mode)' : ''}...`);
  
  try {
    // Check if dependencies are installed
    try {
      require('archiver');
      if (!isDev) {
        try {
          require('terser');
          require('clean-css');
        } catch (e) {
          console.log('Installing optimization dependencies...');
          execSync('npm install --no-save terser clean-css-cli');
        }
      }
    } catch (e) {
      console.log('Installing required dependencies...');
      execSync('npm install --no-save archiver');
    }
    
    // Clean directories
    cleanDirectories();
    
    // Copy files
    copyFiles();
    
    // Optimize assets
    optimizeAssets();
    
    // Validate essential files
    validateFiles();
    
    // Create the ZIP file
    createZipFile();
    
  } catch (error) {
    console.error('❌ Build failed:', error);
    
    // Clean up temp directory if exists
    if (fs.existsSync(tempDir)) {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
    
    process.exit(1);
  }
}

// Run the build process
build(); 