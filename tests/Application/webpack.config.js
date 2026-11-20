const fs = require('fs');
const path = require('path');
const Encore = require('@symfony/webpack-encore');
const SyliusAdmin = require('@sylius-ui/admin');
const SyliusShop = require('@sylius-ui/shop');

const pluginDir = path.resolve(__dirname, '../..');

/**
 * Merges several controllers.json files into one file for the Stimulus bridge.
 * A package listed in more than one file keeps the controllers of each, and a later file wins per controller.
 * Encore resolves entrypoint paths against the directory of the controllers.json, so they're rewritten for the merged file.
 */
function mergeControllers(filePaths) {
  const mergedDir = path.resolve(__dirname, 'var/cache/webpack');
  const controllers = {};
  const entrypoints = {};

  filePaths.forEach((filePath) => {
    const json = JSON.parse(fs.readFileSync(filePath, 'utf-8'));

    Object.entries(json.controllers || {}).forEach(([packageName, packageControllers]) => {
      controllers[packageName] = { ...controllers[packageName], ...packageControllers };
    });

    Object.entries(json.entrypoints || {}).forEach(([name, entrypointPath]) => {
      entrypoints[name] = path.relative(mergedDir, path.resolve(path.dirname(filePath), entrypointPath));
    });
  });

  fs.mkdirSync(mergedDir, { recursive: true });

  const mergedFile = path.join(mergedDir, 'controllers.merged.admin.json');
  fs.writeFileSync(mergedFile, JSON.stringify({ controllers, entrypoints }, null, 2));

  return mergedFile;
}

// Sylius' admin build without a Stimulus app, like in Sylius-Standard. The app.admin build below starts it
const adminConfig = SyliusAdmin.getBaseWebpackConfig(path.resolve(__dirname));
const shopConfig = SyliusShop.getWebpackConfig(path.resolve(__dirname));

Encore
  .setOutputPath('public/build/app/admin')
  .setPublicPath('/build/app/admin')
  .addEntry('app-admin-entry', path.resolve(__dirname, 'assets/admin/entrypoint.js'))
  .addEntry('plugin-admin-entry', path.resolve(pluginDir, 'assets/admin/entrypoint.js'))
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .enableSassLoader()
  // Sylius' controllers.json comes from the installed version, because the list differs between minors (2.3 adds theme-switcher)
  .enableStimulusBridge(mergeControllers([
    require.resolve('@sylius/admin-bundle/controllers.json'),
    path.resolve(pluginDir, 'assets/admin/controllers.json'),
  ]));

const appAdminConfig = Encore.getWebpackConfig();

appAdminConfig.externals = { ...appAdminConfig.externals, window: 'window', document: 'document' };
appAdminConfig.name = 'app.admin';
// The plugin's assets are outside this directory, so imports from them can't find this node_modules on their own
appAdminConfig.resolve.modules = [path.resolve(__dirname, 'node_modules'), 'node_modules'];

module.exports = [shopConfig, adminConfig, appAdminConfig];
