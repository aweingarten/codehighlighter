<?php

/**
 * @file
 * Contains \Drupal\codehighlighter\CodeHighlighterManager.
 */

namespace Drupal\codehighlighter;

class CodeHighlighterManager {
    
    /**
     * Inject codehighlighter on every page except the listed pages.
     */
    const SYNTAXHIGHLIGHTER_INJECT_EXCEPT_LISTED = 0;

    /**
     * Inject codehighlighter on only the listed pages.
     */
    const SYNTAXHIGHLIGHTER_INJECT_IF_LISTED = 1;

    /**
     * Inject codehighlighter if the associated PHP code returns TRUE.
     */
    const SYNTAXHIGHLIGHTER_INJECT_PHP = 2;

    const SYNTAXHIGHLIGHTER_PHP_PERMISSION= 'use PHP for codehighlighter js/css code inject control';

    /**
     * Use a completely none sense word for replacement when filtering so there is
     * absolutely no chance this will conflict with something in content text
     */
    const SYNTAXHIGHLGHTER_TAG_STRING = '-_sYnTaXhIgHlIgHtEr_-';


    
    
    private $config;
            
    function __constructor(){
        $this->config = \Drupal::config('codehighlighter.settings');
    }

    public static function filter_tips($filter, $format, $long = FALSE) {
      $config = \Drupal::config('codehighlighter.settings');
      $tag_name = $config->get('codehighlighter_tagname');
      $tip = t('Syntax highlight code surrounded by the <code>!ex0</code> tags, where !lang is one of the following language brushes: %brushes.',
               array('!ex0' => "&lt;$tag_name class=\"brush: <i>lang</i>\"&gt;...&lt;/$tag_name&gt;",
                     '!lang' => '<i>lang</i>',
                     '%brushes' => implode(', ', self::_get_enabled_language_brushes()),
                    )
               );
      if ($long) {
        $tip .= ' ' . t('See <a href="!url0">the CodeHighlighter javascript library site</a> for additional helps.',
                           array('!url0' => 'http://alexgorbatchev.com/'));
      }
      return $tip;
    }
    
    

    private function _page_match() {
      $inject = $this->config->get('inject');           
      $pages = $this->config->get('pages');

      if ($inject != self::SYNTAXHIGHLIGHTER_INJECT_PHP) {
        $path = drupal_get_path_alias($_GET['q']);
        // Compare with the internal and path alias (if any).
        $page_match = drupal_match_path($path, $pages);
        if ($path != $_GET['q']) {
          $page_match = $page_match || drupal_match_path($_GET['q'], $pages);
        }
        return !($inject xor $page_match);
      }
    /*  else {
        // if the PHP module is not enabled, we just return FALSE
        // which just ends up disabling the codehighlighter
        return function_exists('php_eval') && php_eval($pages);
      }*/
    }

    /**
     * @return an array of all enabled language brushes
     */
    public static function _get_enabled_language_brushes() {
      $config = \Drupal::config('codehighlighter.settings');  
      $brushes = &drupal_static(__FUNCTION__);
      if (!isset($brushes)) {
        $brushes = array();
        foreach ($config->get('enabled_languages') as $val) {
          if ($val) {
            $brushes[] = strtolower(substr(substr($val, 7),0,-3));
          }
        }
      }
      return $brushes;
    }

    /**
     * Escape the content text in preparation for filtering:
     * 
     *  - change all codehighlighter <pre> tag pairs to {-_sYnTaXhIgHlIgHtEr_-}
     *    {/-_sYnTaXhIgHlIgHtEr_-} pair (so other filters would not mess with them
     *
     * Precondition: all the open/close tags much match because search is done on
     * pair by pair basis. If match is not even, do nothing.
     * 
     * All HTML tags and entities inside the CodeHighlighter must be properly
     * escape. For example, if you show HTML code, change
     * 
     *  - '<' to '&lt;': e.g.  <pre> -> &lt;pre>, <html> -> &lt;html>
     *  - neutralize & in entity: e.g.: &gt; -> &amp;gt;
     * 
     * @param string $text
     *   the content text to be filtered
     * @return
     *   the escape content text
     */
    private function _do_filter_prepare($text) {
      $tag_name = $this->config->get('tagname');
      $pattern = "#<$tag_name\\s*([^>]*)>|</\\s*$tag_name>#";
      preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
      $output = '';
      $at = 0;
      $n = count($matches);
      // do nothing if open/close tag match is not even
      if ($n % 2) {
        return $text;
      }
      for ($i = 0 ; $i < $n ; ) {
        $open_tag = $matches[$i++];
        $close_tag = $matches[$i++];
        if (strpos($open_tag[1][0], 'brush:')) {
          $output .= substr($text, $at, $open_tag[0][1] - $at);
          $begin = $open_tag[0][1] + strlen($open_tag[0][0]);
          $length = $close_tag[0][1] - $begin;
          $output .= '{' . self::SYNTAXHIGHLGHTER_TAG_STRING . ' ' . $open_tag[1][0] . '}' .
                      substr($text, $begin, $length) .
                      '{/' . self::SYNTAXHIGHLGHTER_TAG_STRING . '}';
          $at = $close_tag[0][1] + strlen($close_tag[0][0]);
        }
      }
      $output .= substr($text, $at);
      return $output;
    }


    /**
     * Revert back to <pre> tag
     */
    private function _do_filter_process($text) {
      $patterns = array(
        '#{' . SYNTAXHIGHLGHTER_TAG_STRING . ' ([^}]+)}#',
        '#{/' . SYNTAXHIGHLGHTER_TAG_STRING . '}#',
      );
      $tag_name = $this->config->get('tagname');
      $replacements = array(
        "<$tag_name $1>",
        "</$tag_name>",
      );
      return preg_replace($patterns, $replacements, $text);
    }


    /**
     * Validate on comment input text to be sure there is no bad
     * {codehighlighter} tags
     */
    private function _comment_validate($form, &$form_state) {
      if (isset($form_state['values']['comment_body'])) {
        foreach ($form_state['values']['comment_body'] as $lang => $v) {
          if (_format_has_codehighlighter_filter(isset($v[0]['format']) ? $v[0]['format'] : filter_fallback_format())) {
            _validate_input("comment_body][$lang][0][value", $v[0]['value']);
          }
        }
      }
    }


    /**
     * Check for error with codehighlighter input
     *
     * @param string $field
     *   what input field are we checking? We do form_set_error on this if
     *   any error is found
     * @param string $text
     *   the input text to check for
     */
    private function _validate_input($field, $text) {
      $tag_name = $this->config->get('tagname');
      $errors = array();
      

      // check for balance open/close tags
      preg_match_all("#<$tag_name\\s*[^>]*>#", $text, $matches_open);
      preg_match_all("#</\\s*$tag_name\\s*>#", $text, $matches_close);
      if (count($matches_open[0]) != count($matches_close[0])) {
        $errors[] = t('Unbalanced !tag tags.', array('!tag' => "&lt;$tag_name&gt;"));
      }

      // make sure no nesting
      preg_match_all("#<$tag_name\\s*[^>]*>.*</\\s*$tag_name\\s*>#sU", $text, $matches_pair);
      if (count($matches_pair[0]) != count($matches_open[0]) || count($matches_pair[0]) != count($matches_close[0])) {
        $errors[] = t('!tag tags cannot be nested.', array('!tag' => "&lt;$tag_name&gt;"));
      }

      if (!empty($errors)) {
        form_set_error($field, implode(' ', $errors));
      }
    }


    /**
     * @return the directory path where the codehighlighter js lib is installed, NULL if not found
     */
    public function get_lib_location() {
      $result = $this->config->get('lib_location');
      if (!$result) {
        $result = _codehighlighter_scan_lib_location();        
        $this->config->set('lib_location', $result);
        $this->config->save();
        
        // library location may have changed, recreate the setup script if the lib
        // is found
        if ($result) {
          _codehighlighter_setup_autoloader_script();
        }
      }
      return $result;
    }


    /**
     * Do an exhaustive scan of file directories for the location of the codehighlighter js lib,
     * Allow the codehighlighter js library to be installed in any of the following
     * locations and under any sub-directory (except 'src'):
     *   1) codehighlighter module directory
     *   2) sites/<site_domain>/files    (whereever the file_directory_path() is)
     *   3) sites/all/libraries
     *   4) the install profile libraries directory
     * @return the file location of the js lib or NULL if not found
     */
    private function _scan_lib_location() {
      $directories = array(
        drupal_get_path('module', 'codehighlighter'),
        PublicStream::basePath(),
        'sites/all/libraries',
      );
      
      $profile = drupal_get_profile();
      $directories[] = "profiles/$profile/libraries";

      foreach ($directories as $d) {
        // note: file_scan_directory() returns a empty array if no file is found
        // in which case the foreach loop is not enter
        foreach (file_scan_directory($d, '/shCore\.js$/', array('nomask' => '/(\.\.?|CVS|src|pupload|plupload)$/')) as $filename => $file_info) {
          // the path to codehighlighter lib, (-18 to chop off "/scripts/shCore.js"
          // part at the end
          return substr($filename, 0, -18);
        }
      }
      return NULL;
    }

    private function _format_has_codehighlighter_filter($format_id) {
      return array_key_exists('codehighlighter', filter_list_format($format_id));
    }

    
    /**
     * Create the autoload setup script file. Must call this whenever lib
     * location  and/or the enable brushes change.  Make sure never call this
     * if the js lib is not found
     */
    private function _setup_autoloader_script() {
      $path = 'public://codehighlighter.autoloader.js';
      if ($this->config->get('use_autoloader')) {
        // use variable_get() instead of _codehighlighter_get_lib_location()
        // because this function is called only if the lib location is found
        $script_path = base_path() . $this->config->get('lib_location') . '/scripts/';
        $script_data = "/*
     * This file is generated by the Syntaxhighlighter module
     */";
                
        $script_data .= "\nfunction codehighlighterAutoloaderSetup() {\n  CodeHighlighter.autoloader(\n";
        $need_ending = FALSE;
        $brushes = $this->config->get('enabled_languages');
        foreach ($brushes as $b) {
          if ($b) {
            if ($need_ending) {
              $script_data .= ",\n";
            }
            $alias = strtolower(substr(substr($b, 7), 0, -3));
            $script_data .= "    '$alias $script_path$b'";
            $need_ending = TRUE;
          }
        }
        $script_data .= "\n);\n}\n";
        file_unmanaged_save_data($script_data, $path, FILE_EXISTS_REPLACE);
      }
      // check if the file exists before deleting it.
      else if (file_exists($path)) {
        file_unmanaged_delete($path);
      }
    }

  public function setup() {
    
  if (!_page_match()) {
    return;
  }

  $lib_location = self::get_lib_location();
  $styles_path = $lib_location . '/styles/';
  $scripts_path = $lib_location . '/scripts/';
  $js_options = array('type' => 'file', 'group' => JS_DEFAULT, 'every_page' => TRUE);
  
  drupal_add_css($styles_path . 'shCore.css');
  $theme = variable_get('theme', 'shThemeDefault.css');
  drupal_add_css($styles_path . $theme);

  drupal_add_js($scripts_path . 'shCore.js', $js_options);
  if ($config->get('legacy_mode')) {
    drupal_add_js($scripts_path . 'shLegacy.js', $js_options);
  }

  if ($config->get('use_autoloader')) {
    drupal_add_js($scripts_path . 'shAutoloader.js', $js_options);
    drupal_add_js(PublicStream::basePath() . '/codehighlighter.autoloader.js', $js_options);
    $settings['useAutoloader'] = TRUE;
  }
  else {
    $enabled_languages = $config->get('enabled_languages');
    foreach ($enabled_languages as $lang) {
      if (!empty($lang)) {
        drupal_add_js($scripts_path . $lang, $js_options);
      }
    }
  }

  $tag_name = $config->get('tagname');
  if ($tag_name !== 'pre') {
    $settings['tagName'] = $tag_name;
  }
  if (file_exists($scripts_path . 'clipboard.swf')) {
    $settings['clipboard'] = base_path() . $scripts_path . 'clipboard.swf';
  }

  if (isset($settings)) {
    drupal_add_js(array('codehighlighter' => $settings), 'setting');
  }
  
  if ($defaultExpression = $this->config->get('default_expressions')) {
    drupal_add_js($defaultExpression, 'inline');
  }

  drupal_add_js(drupal_get_path('module', 'codehighlighter') . '/codehighlighter.min.js', array('type' => 'file', 'scope' => 'footer', 'group' => JS_DEFAULT, 'every_page' => TRUE));
}



} 