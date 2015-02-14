<?php
/**
 * @file
 * Contains \Drupal\CodeHighlighter\Form\CodeHighlighterAdminSettingsForm.
 */

namespace Drupal\codehighlighter\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\codehighlighter;

/**
 * Configure Syntax Highlighter settings for this site.
 */
class CodeHighlighterAdminSettingsForm extends ConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'syntaxjs_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('codehighlighter.settings');

    
    // delete the variable to force a re-scan of library location just in case
  /*variable_del('codehighlighter_lib_location');
  $path = _codehighlighter_get_lib_location();
  if (!$path) {
    drupal_set_message(t('The codehighlighter javascript library is not found. Consult <a href="!readme">README.txt</a> for help on how to install it, then <a href="!reload">reload</a> this page.',
                         array('!readme' => '/' . drupal_get_path('module', 'codehighlighter') . '/README.txt',
                               '!reload' => 'admin/config/content/codehighlighter')),
                         'error');
    return array();
  }*/

  $autoloader_available = file_exists($path . '/scripts/shAutoloader.js');

  $files = file_scan_directory($path . '/scripts', '/shBrush.*\.js/');
  foreach ($files as $file) {
    $lang_options[$file->filename] = substr($file->name, 7);
  }
  ksort($lang_options);
  $form['codehighlighter_enabled_languages'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Enabled languages'),
    '#options' => $lang_options,
    '#default_value' => $config->get('enabled_languages'),
    '#description' => t('Only the selected languages will be enabled and its corresponding required Javascript brush files loaded.') . ($autoloader_available ? ' ' . t('If you enable "Use autoloader" below, then the brushes are dynamically loaded on demand.') : ''),
    '#multicolumn' => array('width' => 3),
    '#checkall' => TRUE,
  );

  if ($autoloader_available) {
    $form['codehighlighter_use_autoloader'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use autoloader'),
      '#default_value' => $config->get('use_autoloader'),
      '#attached' => array(
        'js' => array(
          array(
            'type' => 'file',
            'data' => drupal_get_path('module', 'codehighlighter') . '/check_all_languages.js',
          ),
        ),
      ),
    );
  }
  else {

      
     $config->set('use_autoloader', FALSE);
     $config->save();
    
    $form['codehighlighter_use_autoloader'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use autoloader'),
      '#default_value' => FALSE,
      '#attributes' => array('disabled' => 'disabled'),
      '#description' => t('Autoloader is not available, update to the latest version of the Syntaxhighlighter javascript library to get this functionality.'),
    );
  }

  $files = file_scan_directory($path . '/styles', '/shTheme.*\.css/', array('nomask' => '/(\.\.?|CVS|shThemeDefault.css)$/'));
  foreach ($files as $file) {
    $theme_options[$file->filename] = substr($file->name, 7);
  }
  ksort($theme_options);
  $theme_options = array_merge(array('shThemeDefault.css' => 'Default'), $theme_options);
  $form['codehighlighter_theme'] = array(
    '#type' => 'radios',
    '#title' => t('Theme'),
    '#description' => t('Choose a syntax highlight theme.'),
    '#options' => $theme_options,
    '#default_value' => $config->get('theme'),
    '#multicolumn' => array('width' => 2),
  );

  $form['codehighlighter_tagname'] = array(
    '#type' => 'textfield',
    '#title' => t('Tag name'),
    '#required' => TRUE,
    '#description' => t('Use different tag to markup code.'),
    '#default_value' => $config->get('tagname'),
    '#size' => 10,
  );
  $form['codehighlighter_legacy_mode'] = array(
    '#type' => 'radios',
    '#title' => t('Legacy mode'),
    '#description' => t('Enable pre-2.0 style markup support.'),
    '#options' => array(t('Disabled'), t('Enabled')),
    '#default_value' => $config->get('legacy_mode'),
  );

  $form['inject_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Syntaxhighlighter js/css code inject settings'),
  );
  
  if ( $config->get('inject') == \Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_INJECT_PHP) {
    $form['codehighlighter_inject'] = array(
      '#type' => 'value',
      '#value' => \Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_INJECT_PHP,
    );
    $form['codehighlighter_pages'] = array(
      '#type' => 'value',
      '#value' => $config->get('pages'),
    );
    if (!$has_php_access) {
      $permission = codehighlighter_permission();
      $messages[] = t('You do not have the "%permission" permission to change these settings.', array('%permission' => $permission[\Drupal\CodeHighlighter\Controller\CodeHighlighterController::SYNTAXHIGHLIGHTER_PHP_PERMISSION]['title']));
    }
    if (!function_exists('php_eval')) {
        $messages[] = t('The "%module_name" module is disabled, re-enable the module to change these settings. Because the "%module_name" module is disabled, syntax highlighting is effectively disabled on every page.', array('%module_name' => t('PHP filter')));
    }
    $items = array(
      'items' => $messages,
      'type' => 'ul',
      'attributes' => array('class' => array('messages', 'warning')),
    );
    $form['inject_settings']['messages'] = array(
      '#type' => 'markup',
      '#markup' => theme('item_list', $items),
    );
  }
  else {
    $options = array(\Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_INJECT_EXCEPT_LISTED => t('Inject on all pages except those listed'),
                     \Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_INJECT_IF_LISTED => t('Inject on only the listed pages'));
    $description = t("Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", array('%blog' => 'blog', '%blog-wildcard' => 'blog/*', '%front' => '<front>'));

    $title = t('Pages');
    if ($has_php_access && function_exists('php_eval')) {
      $options[\Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_INJECT_PHP] = t('Inject if the following PHP code returns <code>TRUE</code> (PHP-mode, experts only)');
      $description .= ' ' . t('If the PHP-mode is chosen, enter PHP code between %php. Note that executing incorrect PHP-code can break your Drupal site.', array('%php' => '<?php ?>'));
      $title = t('Pages or PHP code');
    }
    else {
    // show some friendly info message if PHP is not available because...
    if (!$has_php_access && !function_exists('php_eval')) {
        $permission = codehighlighter_permission();
        $php_info_message = t('You need to have the "%permission" permission and enable the "%module_name" module to use PHP code here.',
                              array('%permission' => $permission[\Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_PHP_PERMISSION]['title'], '%module_name' => t('PHP filter')));
    }
    elseif (!$has_php_access) {
        $permission = codehighlighter_permission();
        $php_info_message = t('You need to have the "%permission" permission to use PHP code here.',
                              array('%permission' => $permission[\Drupal\codehighlighter\CodeHighlighterManager::SYNTAXHIGHLIGHTER_PHP_PERMISSION]['title']));
    }
    /*elseif (!function_exists('php_eval')) {
        $php_info_message = t('Enable the "%module_name" module to use PHP code here.', array('%module_name' => t('PHP filter')));
    }*/
    }

    $form['inject_settings']['codehighlighter_inject'] = array(
      '#type' => 'radios',
      '#title' => t('Inject js/css code on specific pages'),
      '#options' => $options,
      '#default_value' => $config->get('inject'),
    );
    if (isset($php_info_message)) {
    $form['inject_settings']['codehighlighter_inject']['#description'] = $php_info_message;
    }
    $form['inject_settings']['codehighlighter_pages'] = array(
      '#type' => 'textarea',
      '#title' => '<span class="element-invisible">' . $title . '</span>',
      '#default_value' => $config->get('pages'),
      '#description' => $description,
    );
  }
  
  $form['codehighlighter_default_expressions'] = array(
    '#type' => 'textarea',
    '#title' => t('Default expressions'),
    '#default_value' => $config->get('expressions'),
    '#description' => t('Enter syntaxhihglighter default settings javascript expressions, e.g. !example. To turn off clipboardSwf, use !swfoff. See the <a href="!link">codehighlighter js lib doc page</a> for details. Note: these default settings affect the entire site unless they are overridden locally.',
                         array('!example' => '<code>CodeHighlighter.defaults[\'auto-links\'] = true;CodeHighlighter.defaults[\'gutter\'] = false;</code>',
                               '!swfoff' => '<code>CodeHighlighter.config.clipboardSwf = undefined;</code>',
                               '!link' => 'http://alexgorbatchev.com/CodeHighlighter/',
                         )),
  );

 
    $form['#submit'][] = '_codehighlighter_setup_autoloader_script';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Trim custom dimensions and metrics.
    foreach ($form_state->getValue(array('google_analytics_custom_dimension', 'indexes')) as $dimension) {
      $form_state->setValue(array('google_analytics_custom_dimension', 'indexes', $dimension['index'], 'value'), trim($dimension['value']));
      // Remove empty values from the array.
      if (!Unicode::strlen($form_state->getValue(array('google_analytics_custom_dimension', 'indexes', $dimension['index'], 'value')))) {
        $form_state->unsetValue(array('google_analytics_custom_dimension', 'indexes', $dimension['index']));
      }
    }
    $form_state->setValue('google_analytics_custom_dimension', $form_state->getValue(array('google_analytics_custom_dimension', 'indexes')));

    foreach ($form_state->getValue(array('google_analytics_custom_metric', 'indexes')) as $metric) {
      $form_state->setValue(array('google_analytics_custom_metric', 'indexes', $metric['index'], 'value'), trim($metric['value']));
      // Remove empty values from the array.
      if (!Unicode::strlen($form_state->getValue(array('google_analytics_custom_metric', 'indexes', $metric['index'], 'value')))) {
        $form_state->unsetValue(array('google_analytics_custom_metric', 'indexes', $metric['index']));
      }
    }
    $form_state->setValue('google_analytics_custom_metric', $form_state->getValue(array('google_analytics_custom_metric', 'indexes')));

    // Trim some text values.
    $form_state->setValue('google_analytics_account', trim($form_state->getValue('google_analytics_account')));
    $form_state->setValue('google_analytics_pages', trim($form_state->getValue('google_analytics_pages')));
    $form_state->setValue('google_analytics_cross_domains', trim($form_state->getValue('google_analytics_cross_domains')));
    $form_state->setValue('google_analytics_codesnippet_before', trim($form_state->getValue('google_analytics_codesnippet_before')));
    $form_state->setValue('google_analytics_codesnippet_after', trim($form_state->getValue('google_analytics_codesnippet_after')));
    $form_state->setValue('google_analytics_roles', array_filter($form_state->getValue('google_analytics_roles')));
    $form_state->setValue('google_analytics_trackmessages', array_filter($form_state->getValue('google_analytics_trackmessages')));

    // Replace all type of dashes (n-dash, m-dash, minus) with the normal dashes.
    $form_state->setValue('google_analytics_account', str_replace(array('–', '—', '−'), '-', $form_state->getValue('google_analytics_account')));

    if (!preg_match('/^UA-\d+-\d+$/', $form_state->getValue('google_analytics_account'))) {
      $form_state->setErrorByName('google_analytics_account', t('A valid Google Analytics Web Property ID is case sensitive and formatted like UA-xxxxxxx-yy.'));
    }

    // If multiple top-level domains has been selected, a domain names list is required.
    if ($form_state->getValue('google_analytics_domain_mode') == 2 && $form_state->isValueEmpty('google_analytics_cross_domains')) {
      $form_state->setErrorByName('google_analytics_cross_domains', t('A list of top-level domains is required if <em>Multiple top-level domains</em> has been selected.'));
    }
    // Clear the domain list if cross domains are disabled.
    if ($form_state->getValue('google_analytics_domain_mode') != 2) {
      $form_state->setValue('google_analytics_cross_domains', '');
    }

    // Disallow empty list of download file extensions.
    if ($form_state->getValue('google_analytics_trackfiles') && $form_state->isValueEmpty('google_analytics_trackfiles_extensions')) {
      $form_state->setErrorByName('google_analytics_trackfiles_extensions', t('List of download file extensions cannot empty.'));
    }
    // Clear obsolete local cache if cache has been disabled.
    if ($form_state->isValueEmpty('google_analytics_cache') && $form['advanced']['google_analytics_cache']['#default_value']) {
      google_analytics_clear_js_cache();
    }

    // This is for the Newbie's who cannot read a text area description.
    if (stristr($form_state->getValue('google_analytics_codesnippet_before'), 'google-analytics.com/analytics.js')) {
      $form_state->setErrorByName('google_analytics_codesnippet_before', t('Do not add the tracker code provided by Google into the javascript code snippets! This module already builds the tracker code based on your Google Analytics account number and settings.'));
    }
    if (stristr($form_state->getValue('google_analytics_codesnippet_after'), 'google-analytics.com/analytics.js')) {
      $form_state->setErrorByName('google_analytics_codesnippet_after', t('Do not add the tracker code provided by Google into the javascript code snippets! This module already builds the tracker code based on your Google Analytics account number and settings.'));
    }
    if (preg_match('/(.*)<\/?script(.*)>(.*)/i', $form_state->getValue('google_analytics_codesnippet_before'))) {
      $form_state->setErrorByName('google_analytics_codesnippet_before', t('Do not include the &lt;script&gt; tags in the javascript code snippets.'));
    }
    if (preg_match('/(.*)<\/?script(.*)>(.*)/i', $form_state->getValue('google_analytics_codesnippet_after'))) {
      $form_state->setErrorByName('google_analytics_codesnippet_after', t('Do not include the &lt;script&gt; tags in the javascript code snippets.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics.settings');
    $config
      ->set('account', $form_state->getValue('google_analytics_account'))
      ->set('cross_domains', $form_state->getValue('google_analytics_cross_domains'))
      ->set('codesnippet.create', $form_state->getValue('google_analytics_codesnippet_create'))
      ->set('codesnippet.before', $form_state->getValue('google_analytics_codesnippet_before'))
      ->set('codesnippet.after', $form_state->getValue('google_analytics_codesnippet_after'))
      ->set('custom.dimension', $form_state->getValue('google_analytics_custom_dimension'))
      ->set('custom.metric', $form_state->getValue('google_analytics_custom_metric'))
      ->set('domain_mode', $form_state->getValue('google_analytics_domain_mode'))
      ->set('track.files', $form_state->getValue('google_analytics_trackfiles'))
      ->set('track.files_extensions', $form_state->getValue('google_analytics_trackfiles_extensions'))
      ->set('track.linkid', $form_state->getValue('google_analytics_tracklinkid'))
      ->set('track.userid', $form_state->getValue('google_analytics_trackuserid'))
      ->set('track.mailto', $form_state->getValue('google_analytics_trackmailto'))
      ->set('track.messages', $form_state->getValue('google_analytics_trackmessages'))
      ->set('track.outbound', $form_state->getValue('google_analytics_trackmailto'))
      ->set('track.site_search', $form_state->getValue('google_analytics_site_search'))
      ->set('track.adsense', $form_state->getValue('google_analytics_trackadsense'))
      ->set('track.displayfeatures', $form_state->getValue('google_analytics_trackdisplayfeatures'))
      ->set('privacy.anonymizeip', $form_state->getValue('google_analytics_tracker_anonymizeip'))
      ->set('privacy.donottrack', $form_state->getValue('google_analytics_privacy_donottrack'))
      ->set('cache', $form_state->getValue('google_analytics_cache'))
      ->set('debug', $form_state->getValue('google_analytics_debug'))
      ->set('visibility.pages_enabled', $form_state->getValue('google_analytics_visibility_pages'))
      ->set('visibility.pages', $form_state->getValue('google_analytics_pages'))
      ->set('visibility.roles_enabled', $form_state->getValue('google_analytics_visibility_roles'))
      ->set('visibility.roles', $form_state->getValue('google_analytics_roles'))
      ->set('visibility.custom', $form_state->getValue('google_analytics_custom'))
      ->save();

    if ($form_state->hasValue('google_analytics_translation_set')) {
      $config->set('translation_set', $form_state->getValue('google_analytics_translation_set'))->save();
    }

    parent::submitForm($form, $form_state);
  }
}

  
