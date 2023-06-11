<?php
/*
  Plugin Name: Pressbooks LTI Tool
  Description: This plugin allows Pressbooks to be integrated with on-line courses using the 1EdTech Learning Tools Interoperability (LTI) specification.
  Version: 1.1.0
  Author: Stephen P Vickers
 */

/*
 *  pressbooks-lti-tool - WordPress module to integrate LTI support with Pressbooks
 *  Copyright (C) 2023  Stephen P Vickers
 *
 *  Author: stephen@spvsoftwareproducts.com
 */

use ceLTIc\LTI\Context;
use ceLTIc\LTI\LineItem;
use ceLTIc\LTI\UserResult;
use ceLTIc\LTI\Outcome;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\Enum\IdScope;
use Pressbooks\Book;

// Prevent loading this file directly
defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (class_exists('ceLTIc\LTI\Platform')) {
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'PressbooksTool.php');
}

/**
 * Current plugin name.
 */
define('PRESSBOOKS_LTI_TOOL_PLUGIN_NAME', 'pressbooks-lti-tool');

/**
 * Check dependent plugins are activated when WordPress is loaded.
 */
function pressbooks_lti_tool_once_wp_loaded()
{
    if (!is_plugin_active('pressbooks/pressbooks.php') || !is_plugin_active('lti-tool/lti-tool.php')) {
        add_action('all_admin_notices', 'pressbooks_lti_tool_show_note_errors_activated');
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function pressbooks_lti_tool_show_note_errors_activated()
{
    $html = '    <div class="notice notice-error">' . "\n" .
        '      <p>The Pressbooks-LTI-Tool plugin requires both the <em>Pressbooks</em> and <em>LTI Tool</em> plugins to be installed and activated first.</p>' . "\n" .
        '    </div>' . "\n";
    $allowed = array('div' => array('class' => true), 'p' => array(), 'em' => array());
    echo(wp_kses($html, $allowed));
}

add_action('wp_loaded', 'pressbooks_lti_tool_once_wp_loaded');

/**
 * Check for requests being sent to this plugin.
 */
function pressbooks_lti_tool_parse_request()
{
    if (isset($_GET[PRESSBOOKS_LTI_TOOL_PLUGIN_NAME])) {
        if (isset($_GET['deeplink'])) {
            require_once('lti-deeplink.php');
            exit;
        } else if (isset($_GET['icon'])) {
            wp_redirect(plugins_url('images/book.png', __FILE__));
            exit;
        }
    }
}

add_action('parse_request', 'pressbooks_lti_tool_parse_request');

/**
 * Override Tool instance to be used by LTI Tool plugin.
 *
 * @param Tool $tool
 * @param ceLTIc\LTI\DataConnector\DataConnector $db_connector
 *
 * @return Tool
 */
function pressbooks_lti_tool_lti_tool($tool, $db_connector)
{
    return new Pressbooks_LTI_Tool($db_connector);
}

add_filter('lti_tool_tool', 'pressbooks_lti_tool_lti_tool', 10, 2);

/**
 * Hide unnecessary options from LTI Tool options page.
 *
 * @param array $hide_options
 *
 * @return array
 */
function pressbooks_lti_tool_hide_options($hide_options)
{
    $hide_options = array();
    $hide_options['uninstallblogs'] = '0';
    $hide_options['adduser'] = '1';
    $hide_options['mysites'] = '1';
    if (function_exists('lti_tool_use_lti_library_v5') && lti_tool_use_lti_library_v5()) {
        $enum = IdScope::Platform;  // Avoids parse error in PHP < 8.1
        $hide_options['scope'] = $enum->value;
    } else {
        $hide_options['scope'] = strval(Tool::ID_SCOPE_GLOBAL);
    }
    $hide_options['saveemail'] = '0';
    $hide_options['homepage'] = '';

    return $hide_options;
}

add_filter('lti_tool_hide_options', 'pressbooks_lti_tool_hide_options', 10, 1);

/**
 * Add options to LTI Tool options page
 */
function pressbooks_lti_tool_init_options()
{
    add_settings_field(
        'pressbooks_hide_navigation', __('Hide Pressbooks navigation?', PRESSBOOKS_LTI_TOOL_PLUGIN_NAME),
        'pressbooks_lti_tool_hide_navigation_callback', 'lti_tool_options_admin', 'lti_tool_options_general_section'
    );
    add_settings_field(
        'pressbooks_grading_rule', __('Default grading rule', PRESSBOOKS_LTI_TOOL_PLUGIN_NAME),
        'pressbooks_lti_tool_grading_rule_callback', 'lti_tool_options_admin', 'lti_tool_options_general_section'
    );
}

function pressbooks_lti_tool_hide_navigation_callback()
{
    $options = lti_tool_get_options();
    $html = sprintf(
        '<input type="checkbox" name="lti_tool_options[pressbooks_hide_navigation]" id="pressbooks_hide_navigation" value="1"%s> <label for="pressbooks_hide_navigation">' . __('Check this box if you want to hide the Pressbooks navigation elements and only display the book content',
            'lti-tool') . '</label>', (!empty($options['pressbooks_hide_navigation'])) ? ' checked' : ''
    );
    $html .= "\n";

    $allowed = array('input' => array('type' => true, 'name' => true, 'id' => true, 'value' => true, 'checked' => true), 'label' => array('for' => true));
    echo(wp_kses($html, $allowed));
}

function pressbooks_lti_tool_grading_rule_callback()
{
    $name = 'pressbooks_grading_rule';
    $options = lti_tool_get_options();
    $current = isset($options[$name]) ? $options[$name] : '';
    $html = sprintf('<select name="lti_tool_options[%s]" id="%s">', esc_attr($name), esc_attr($name));
    $html .= "\n";
    $rules = array(__('Send all attempts', PRESSBOOKS_LTI_TOOL_PLUGIN_NAME) => '',
        __('Send first attempt only', PRESSBOOKS_LTI_TOOL_PLUGIN_NAME) => 'first',
        __('Send best attempt', PRESSBOOKS_LTI_TOOL_PLUGIN_NAME) => 'best');
    foreach ($rules as $key => $value) {
        $selected = ($value === $current) ? ' selected' : '';
        $html .= sprintf('  <option value="%s"%s>%s</option>', esc_attr($value), esc_attr($selected), esc_html($key));
        $html .= "\n";
    }
    $html .= "</select>\n";

    $allowed = array('select' => array('name' => true, 'id' => true), 'option' => array('value' => true, 'selected' => true));
    echo(wp_kses($html, $allowed));
}

add_action('lti_tool_init_options', 'pressbooks_lti_tool_init_options');

/**
 * Add available books, hide navigation and grading rule options to LTI Platform configuration page.
 *
 * @param array $html
 * @param ceLTIc\LTI\Platform $platform
 *
 * @return array
 */
function pressbooks_lti_tool_config_platform($html, $platform)
{
    $books = explode(',', $platform->getSetting('__pressbooks_available_books'));
    $titles = array();
    $sites = get_sites();
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        if (Book::isBook()) {
            $titles[$site->blog_id] = get_bloginfo('name');
        }
        restore_current_blog();
    }
    asort($titles);
    $checked = checked(!empty($platform->getSetting('__pressbooks_hide_navigation')), true, false);
    $rule = $platform->getSetting('__pressbooks_grading_rule');
    $selected = function($value, $current) {
        if ($value === $current) {
            return ' selected';
        } else {
            return '';
        }
    };
    $html['general'] = '        <tr>' . "\n" .
        '          <th scope="row">' . "\n" .
        '            Available books' . "\n" .
        '          </th>' . "\n" .
        '          <td>' . "\n" .
        '            <fieldset>' . "\n" .
        '              <legend class="screen-reader-text">' . "\n" .
        '                <span>Available books</span>' . "\n" .
        '              </legend>' . "\n" .
        '              <label for="lti_tool_pressbooks_available_books">' . "\n" .
        '                <select name="lti_tool_pressbooks_available_books[]" id="lti_tool_pressbooks_available_books" multiple>' . "\n";
    foreach ($titles as $id => $title) {
        $select = '';
        if (in_array($id, $books)) {
            $select = ' selected';
        }
        $html['general'] .= '                  <option value="' . $id . '"' . $select . '>' . $title . '</option>' . "\n";
    }
    $html['general'] .= '                </select>' . "\n" .
        '                 Select which books are available to this platform' . "\n" .
        '              </label>' . "\n" .
        '            </fieldset>' . "\n" .
        '          </td>' . "\n" .
        '        </tr>' . "\n" .
        '        <tr>' . "\n" .
        '          <th scope="row">' . "\n" .
        '            Hide Pressbooks navigation?' . "\n" .
        '          </th>' . "\n" .
        '          <td>' . "\n" .
        '            <fieldset>' . "\n" .
        '              <legend class="screen-reader-text">' . "\n" .
        '                <span>Hide Pressbooks navigation?</span>' . "\n" .
        '              </legend>' . "\n" .
        '              <label for="lti_tool_pressbooks_hide_navigation">' . "\n" .
        '                <input name="lti_tool_pressbooks_hide_navigation" type="checkbox" id="lti_tool_pressbooks_hide_navigation" value="true"' . $checked . ' />' . "\n" .
        '                   Check this box if you want to hide the Pressbooks navigation elements and only display the book content' . "\n" .
        '              </label>' . "\n" .
        '            </fieldset>' . "\n" .
        '          </td>' . "\n" .
        '        </tr>' . "\n" .
        '        <tr>' . "\n" .
        '          <th scope="row">' . "\n" .
        '            Grading rule' . "\n" .
        '          </th>' . "\n" .
        '          <td>' . "\n" .
        '            <fieldset>' . "\n" .
        '              <legend class="screen-reader-text">' . "\n" .
        '                <span>Grading rule</span>' . "\n" .
        '              </legend>' . "\n" .
        '              <label for="lti_tool_pressbooks_grading_rule">' . "\n" .
        '                <select name="lti_tool_pressbooks_grading_rule" id="lti_tool_pressbooks_grading_rule">' . "\n" .
        '                  <option value=""' . $selected('', $rule) . '>Send all attempts</option>' . "\n" .
        '                  <option value="first"' . $selected('first', $rule) . '>Send first attempt only</option>' . "\n" .
        '                  <option value="best"' . $selected('best', $rule) . '>Send best attempt</option>' . "\n" .
        '                </select>' . "\n" .
        '                 Select which attempts should be sent to the platform' . "\n" .
        '              </label>' . "\n" .
        '            </fieldset>' . "\n" .
        '          </td>' . "\n" .
        '        </tr>' . "\n";

    return $html;
}

add_filter('lti_tool_config_platform', 'pressbooks_lti_tool_config_platform', 10, 2);

/**
 * Ensure added platform configuration options are saved.
 *
 * @param ceLTIc\LTI\Platform $platform
 * @param array $options
 * @param array $data
 *
 * @return ceLTIc\LTI\Platform
 */
function pressbooks_lti_tool_save_platform($platform, $options, $data)
{
    $books = null;
    if (isset($data['lti_tool_pressbooks_available_books'])) {
        $books = implode(',', $data['lti_tool_pressbooks_available_books']);
    }
    $platform->setSetting('__pressbooks_available_books', $books);

    $hide = null;
    if (isset($data['lti_tool_pressbooks_hide_navigation'])) {
        $hide = (sanitize_text_field($data['lti_tool_pressbooks_hide_navigation']) === 'true') ? 'true' : null;
    } else if (isset($options['pressbooks_hide_navigation'])) {
        $hide = $options['pressbooks_hide_navigation'];
    }
    $platform->setSetting('__pressbooks_hide_navigation', $hide);

    $rule = null;
    if (isset($data['lti_tool_pressbooks_grading_rule'])) {
        $rule = sanitize_text_field($data['lti_tool_pressbooks_grading_rule']);
        if (empty($rule)) {
            $rule = null;
        }
    } else if (isset($options['pressbooks_grading_rule'])) {
        $rule = $options['pressbooks_grading_rule'];
    }
    $platform->setSetting('__pressbooks_grading_rule', $rule);

    return $platform;
}

add_filter('lti_tool_save_platform', 'pressbooks_lti_tool_save_platform', 10, 3);

/**
 * Return outcome to LTI platform when an H5P activity is submitted.
 *
 * @global type $lti_tool_session
 * @global type $lti_tool_data_connector
 *
 * @param type $data
 * @param type $result_id
 * @param type $content_id
 * @param type $user_id
 */
function pressbooks_lti_tool_h5p_result(&$data, $result_id, $content_id, $user_id)
{
    global $lti_tool_session, $lti_tool_data_connector;

    $context = Context::fromRecordId($lti_tool_session['contextpk'], $lti_tool_data_connector);
    if ($context->hasLineItemService() && !empty($lti_tool_session['userpk'])) {
        $rule = $context->getPlatform()->getSetting('__pressbooks_grading_rule');
        if (($rule !== 'first') || empty($result_id)) {
            $plugin = H5P_Plugin::get_instance();
            $content = $plugin->get_content($content_id);
            $resource_id = strval($content_id);
            $label = "H5P: {$content['title']}";
            $tag = 'H5P';
            $points_possible = $data['max_score'];
            $lineitems = $context->getLineItems($resource_id);
            if (empty($lineitems)) {
                $lineitem = new LineItem($context->getPlatform(), $label, $points_possible);
                $lineitem->resourceId = $resource_id;
                $lineitem->tag = $tag;
                $context->createLineItem($lineitem);
            } else {
                $lineitem = reset($lineitems);
                if (($lineitem->label !== $label) || ($lineitem->tag !== $tag) || ($lineitem->pointsPossible !== $points_possible)) {
                    $lineitem->label = $label;
                    $lineitem->tag = $tag;
                    $lineitem->pointsPossible = $points_possible;
                    $lineitem->save();
                }
            }
            $user = UserResult::fromRecordId($lti_tool_session['userpk'], $lti_tool_data_connector);
            $save = true;
            if ($rule === 'best') {
                $outcome = $lineitem->readOutcome($user);
                if (!empty($outcome)) {
                    $save = floatval($outcome->getValue()) < floatval($data['score']);
                }
            }
            if ($save) {
                $outcome = new Outcome($data['score'], $points_possible);
                $lineitem->submitOutcome($outcome, $user);
            }
        }
    }
}

add_action('h5p_alter_user_result', 'pressbooks_lti_tool_h5p_result', 10, 4);

/**
 * Restrict user scopes offered for platform configurations.
 *
 * @param array $scopes
 *
 * @return array
 */
function pressbooks_lti_tool_id_scopes($scopes)
{
    if (function_exists('lti_tool_use_lti_library_v5') && lti_tool_use_lti_library_v5()) {
        $enum = IdScope::Global;  // Avoids parse error in PHP < 8.1
        $scope = $enum->value;
    } else {
        $scope = strval(Tool::ID_SCOPE_GLOBAL);
    }
    $pressbooks_scopes = array();
    $pressbooks_scopes[$scope] = $scopes[$scope];

    return $pressbooks_scopes;
}

add_filter('lti_tool_id_scopes', 'pressbooks_lti_tool_id_scopes', 10, 1);

/**
 * Insert script to hide navigation when option has been selected.
 *
 * @global array $lti_tool_session
 */
function pressbooks_lti_tool_navigation()
{
    global $lti_tool_session;

    if (isset($lti_tool_session['hide_nav']) && $lti_tool_session['hide_nav']) {
        echo("<script>\n" .
        "if (window.top !== window.self) {\n" .
        "  document.addEventListener('DOMContentLoaded', function () {\n" .
        "    document.body.classList.add('no-navigation');\n" .
        "  });\n" .
        "}\n" .
        "</script>\n");
    }
}

add_action('wp_head', 'pressbooks_lti_tool_navigation');

function pressbooks_lti_tool_lti_configure_xml($dom)
{
    $dom->getElementsByTagNameNS('http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'title')[0]->childNodes[0]->nodeValue = 'Pressbooks';
    $dom->getElementsByTagNameNS('http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'description')[0]->childNodes[0]->nodeValue = 'Access Pressbooks using LTI';

    return $dom;
}

add_filter('lti_tool_configure_xml', 'pressbooks_lti_tool_lti_configure_xml', 10, 1);

function pressbooks_lti_tool_lti_configure_json($configuration)
{
    $configuration->title = 'Pressbooks';
    $configuration->description = 'Access Pressbooks using LTI';

    return $configuration;
}

add_filter('lti_tool_configure_json', 'pressbooks_lti_tool_lti_configure_json', 10, 1);
