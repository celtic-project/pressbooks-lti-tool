<?php
/*
 *  pressbooks-lti-tool - WordPress module to integrate LTI support with Pressbooks
 *  Copyright (C) 2023  Stephen P Vickers
 *
 *  Author: stephen@spvsoftwareproducts.com
 */

/**
 * Display a page of available books and sections and return the selection to the platform.
 */
use ceLTIc\LTI;
use ceLTIc\LTI\Enum\LtiVersion;
use Pressbooks\Book;

// Prevent loading this file directly
defined('ABSPATH') || exit;

global $lti_tool_data_connector, $lti_tool_session;

$lti_tool_session = lti_tool_get_session();

$platform = LTI\Platform::fromConsumerKey($lti_tool_session['key'], $lti_tool_data_connector);
if (($_SERVER['REQUEST_METHOD'] === 'POST') &&
    isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '-nonce')) {
    LTI\Tool::$defaultTool->platform = $platform;
    $form_params = array();
    if (!empty($_POST['section'])) {
        $sections = $_POST['section'];
        if (!is_array($sections)) {
            $sections = array($sections);
        }
        $items = array();
        foreach ($sections as $section) {
            $parts = explode('-', sanitize_text_field($section), 2);
            $placement = new LTI\Content\Placement(LTI\Content\Placement::TYPE_WINDOW);
            $item = new LTI\Content\LtiLinkItem();
            $item->setMediaType(LTI\Content\Item::LTI_LINK_MEDIA_TYPE);
            $item->setTitle(urldecode($parts[1]));
            $item->setIcon(new LTI\Content\Image(plugins_url(PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '/images/book.png'), 50, 50));
            if (strpos(LTI\Tool::$defaultTool->platform->consumerVersion, 'canvas') === 0) {
                $item->setUrl(get_option('siteurl') . '/?lti-tool');
            }
            $item->addCustom('section', $parts[0]);
            $items[] = $item;
        }
        $form_params['content_items'] = LTI\Content\Item::toJson($items, $lti_tool_session['lti_version']);
    }
    if (!is_null($lti_tool_session['data'])) {
        $form_params['data'] = $lti_tool_session['data'];
    }

// Use same LTI version and signature method as platform
    LTI\Tool::$defaultTool->ltiVersion = LTI\Tool::$defaultTool->platform->ltiVersion;
    LTI\Tool::$defaultTool->signatureMethod = LTI\Tool::$defaultTool->platform->signatureMethod;

    if (function_exists('lti_tool_use_lti_library_v5') && lti_tool_use_lti_library_v5()) {
        $isv1p3 = ($lti_tool_session['lti_version'] === LtiVersion::V1P3);
        $lti_version = $lti_tool_session['lti_version']->value;
    } else {
        $isv1p3 = ($lti_tool_session['lti_version'] === LTI\Util::LTI_VERSION1P3);
        $lti_version = $lti_tool_session['lti_version'];
    }
    if (!$isv1p3) {
        $message_type = 'ContentItemSelection';
    } else {
        $message_type = 'LtiDeepLinkingResponse';
    }
    $form_params = LTI\Tool::$defaultTool->signParameters($lti_tool_session['return_url'], $message_type, $lti_version, $form_params);
    echo(LTI\Util::sendForm($lti_tool_session['return_url'], $form_params));
} else {
    $available_books = explode(',', $platform->getSetting('__pressbooks_available_books'));
    $titles = array();
    $books = array();
    $sites = get_sites();
    foreach ($sites as $site) {
        if (in_array($site->blog_id, $available_books)) {
            switch_to_blog($site->blog_id);
            if (Book::isBook()) {
                $book_structure = Book::getBookStructure();
                $titles[$site->blog_id] = get_bloginfo('name');
                $books[$site->blog_id] = $book_structure;
            }
            restore_current_blog();
        }
    }
    if ($lti_tool_session['accept_multiple']) {
        $type = 'checkbox';
        $suffix = '[]';
        $span = ' <span href="#"{$open}>[Select all]</span>';
    } else {
        $type = 'radio';
        $suffix = '';
        $span = '';
    }

    $allowed = array('html' => array(), 'title' => array(), 'head' => array(),
        'link' => array('rel' => true, 'id' => true, 'href' => true, 'media' => true), 'style' => array(),
        'script' => array(), 'body' => array('onload' => true, 'class' => true), 'h1' => array(),
        'form' => array('action' => true, 'method' => true), 'p' => array(),
        'span' => array('href' => true, 'class' => true),
        'ul' => array('id' => true, 'class' => true), 'li' => array('class' => true),
        'a' => array('href' => true, 'class' => array(), 'onclick' => true),
        'input' => array('id' => true, 'type' => true, 'name' => true, 'value' => true, 'class' => true),
        'label' => array('for' => true));

    $css = '';
    if (file_exists(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pressbooks' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . 'colors-pb.css')) {
        $css = '  <link rel="stylesheet" id="colors-css" href="' . plugins_url('pressbooks/assets/dist/styles/colors-pb.css') . '" />' . "\n";
    }
    $html = '<html>' . "\n" .
        '<head>' . "\n" .
        '  <title>Select content</title>' . "\n" .
        $css .
        '  <style>' . "\n";
    echo(wp_kses($html, $allowed));

    echo('    body {' . "\n" .
    '      margin: 1em;' . "\n" .
    '      font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;' . "\n" .
    '      font-style: normal;' . "\n" .
    '      font-variant: normal;' . "\n" .
    '      font-weight: 400;' . "\n" .
    '    }' . "\n" .
    '    p {' . "\n" .
    '      line-height: 1.5;' . "\n" .
    '    }' . "\n" .
    '    h1 {' . "\n" .
    '      font-size: 1.25em;' . "\n" .
    '      font-style: normal;' . "\n" .
    '      font-variant: normal;' . "\n" .
    '      font-weight: 500;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li {' . "\n" .
    '      list-style-type: none;' . "\n" .
    '      position: relative;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li ul {' . "\n" .
    '      display: none;' . "\n" .
    '      padding-left: 15px' . "\n" .
    '    }' . "\n" .
    '    ul.tree li.open > ul {' . "\n" .
    '      display: block;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li a {' . "\n" .
    '      color: black;' . "\n" .
    '      text-decoration: none;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li a:before { /* the expand/collapse symbols before each item */' . "\n" .
    '      height: 1em;' . "\n" .
    '      font-size: .8em;' . "\n" .
    '      display: block;' . "\n" .
    '      position: absolute;' . "\n" .
    '      left: -1.2em;' . "\n" .
    '      top: .05em;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li > a:not(:last-child):before {' . "\n" .
    '      content: "⊞";' . "\n" .
    '      font-weight: bold;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li.open > a:not(:last-child):before {' . "\n" .
    '      content: "⊟";' . "\n" .
    '      font-weight: normal;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li.open > a:last-child:before {' . "\n" .
    '      content: "";' . "\n" .
    '      font-weight: bold;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li:before, ul.tree li:after {' . "\n" .
    '      position: absolute;' . "\n" .
    '      left: -0.7em;' . "\n" .
    '      border-style: dotted;' . "\n" .
    '      border-color: #909090;' . "\n" .
    '      border-width: 0px;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li:before {' . "\n" .
    '      content: "";' . "\n" .
    '      border-top-width: 1px;' . "\n" .
    '      height: 0;' . "\n" .
    '      width: 8px;' . "\n" .
    '      top: 9px;' . "\n" .
    '    }' . "\n" .
    '    ul.tree li:after {' . "\n" .
    '      content: "";' . "\n" .
    '      border-left-width: 1px;' . "\n" .
    '      height: 100%;' . "\n" .
    '      width: 0px;' . "\n" .
    '      top: 2px;' . "\n" .
    '    }' . "\n" .
    '    ul.tree > li::before {' . "\n" .
    '      width: 0px;' . "\n" .
    '    }' . "\n" .
    '    ul.tree > li::after {' . "\n" .
    '      height: 0px;' . "\n" .
    '    }' . "\n" .
    '    ul.tree > li li:last-child:after {' . "\n" .
    '      height: 8px;' . "\n" .
    '    }' . "\n" .
    '    a.book {' . "\n" .
    '      font-weight: bold;' . "\n" .
    '    }' . "\n" .
    '    li.chapter {' . "\n" .
    '      font-style: italic;' . "\n" .
    '    }' . "\n" .
    '    span {' . "\n" .
    '      cursor: pointer;' . "\n" .
    '      display: none;' . "\n" .
    '    }' . "\n" .
    '    span.open {' . "\n" .
    '      display: inline;' . "\n" .
    '      font-size: small;' . "\n" .
    '    }' . "\n");

    $html = '  </style>' . "\n" .
        '  <script>' . "\n";
    echo(wp_kses($html, $allowed));

    echo("    var onLoad = function() {\n" .
    "      var items = document.querySelectorAll('ul.tree a');\n" .
    "      for (var i = 0; i < items.length; i++) {\n" .
    "        items[i].addEventListener('click', function(e) {\n" .
    "          var span = e.target.nextElementSibling;\n" .
    "          var parent = e.target.parentElement;\n" .
    "          var classes = parent.classList;\n" .
    "          if (classes.contains('open')) {\n" .
    "            classes.remove('open');\n" .
    "            span.classList.remove('open');\n" .
    "          } else {\n" .
    "            classes.add('open');\n" .
    "            span.classList.add('open');\n" .
    "          }\n" .
    "        });\n" .
    "      }\n");
    if ($lti_tool_session['accept_multiple']) {
        echo("      items = document.querySelectorAll('span');\n" .
        "      for (var i = 0; i < items.length; i++) {\n" .
        "        items[i].addEventListener('click', function(e) {\n" .
        "          var check = e.target.innerHTML === '[Select all]';\n" .
        "          if (check) {\n" .
        "            e.target.innerHTML = '[Select none]';\n" .
        "          } else {\n" .
        "            e.target.innerHTML = '[Select all]';\n" .
        "          }\n" .
        "          var checkboxes = e.target.parentElement.querySelectorAll('input');\n" .
        "          for (var j = 0; j < checkboxes.length; j++) {\n" .
        "            checkboxes[j].checked = check;\n" .
        "          }\n" .
        "        });\n" .
        "      }\n" .
        "      items = document.querySelectorAll('input');\n" .
        "      for (var i = 0; i < items.length; i++) {\n" .
        "        items[i].addEventListener('click', function(e) {\n" .
        "          var span = e.target.parentElement.parentElement.previousElementSibling;\n" .
        "          var checked = e.target.checked;\n" .
        "          if (checked) {\n" .
        "            var all = e.target.parentElement.querySelectorAll('input');\n" .
        "            for (var j = 0; j < all.length; j++) {\n" .
        "              checked = checked && all[j].checked;\n" .
        "            }\n" .
        "          }\n" .
        "          if (checked) {\n" .
        "            span.innerHTML = '[Select none]';\n" .
        "          } else {\n" .
        "            span.innerHTML = '[Select all]';\n" .
        "          }\n" .
        "        });\n" .
        "      }\n");
    }
    echo("    };\n");

    $html = '  </script>' . "\n" .
        '</head>' . "\n" .
        '<body onload="onLoad();" class="wp-core-ui admin-color-pb_colors">' . "\n" .
        '  <h1>Select content to be linked</h1>' . "\n" .
        '  <form action="" method="post">' . "\n    " .
        wp_nonce_field(PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '-nonce', '_wpnonce', true, false) . "\n";
    if (empty($books)) {
        $html .= '    <p>' . "\n" .
            '      There is no content available at this time.' . "\n" .
            '    </p>' . "\n";
    } else {
        $open = '';
        if (count($books) <= 1) {
            $open = ' class="open"';
        }
        foreach ($books as $id => $book_structure) {
            $html .= '    <ul class="tree">' . "\n" .
                '      <li' . $open . '"><a class="book" href="#" onclick="return false;">' . $titles[$id] . '</a>' . $span . "\n" .
                '        <ul>' . "\n" .
                '          <li><input id="item-' . $id . '-0" type="' . $type . '" name="section' . $suffix . '" value="' . $id . '.0-Cover Page"> <label for="item-' . $id . '-0">Cover Page</label></li>' . "\n";
            if (!empty($book_structure['front-matter'])) {
                $html .= '            <li class="chapter">Front Matter</li>' . "\n";
                foreach ($book_structure['front-matter'] as $k => $v) {
                    $title = str_replace('"', '&quot;', $v['post_title']);
                    $html .= '            <li><input id="item-' . $id . '-' . $v['ID'] . '" type="' . $type . '" name="section' . $suffix . '" value="' . $id . '.' . $v['ID'] . '-' . $title . '"> <label for="item-' . $id . '-' . $v['ID'] . '">' . $v['post_title'] . '</label></li>' . "\n";
                }
            }
            foreach ($book_structure['part'] as $key => $value) {
                if (!empty($value['chapters'])) {
                    $html .= '             <li class="chapter">' . $value['post_title'] . '</li>' . "\n";
                    foreach ($value['chapters'] as $k => $v) {
                        $title = str_replace('"', '&quot;', $v['post_title']);
                        $html .= '              <li><input id="item-' . $id . '-' . $v['ID'] . '" type="' . $type . '" name="section' . $suffix . '" value="' . $id . '.' . $v['ID'] . '-' . $title . '"> <label for="item-' . $id . '-' . $v['ID'] . '">' . $v['post_title'] . '</label></li>' . "\n";
                    }
                }
            }
            if (!empty($book_structure['back-matter'])) {
                $html .= '            <li class="chapter">Back Matter</li>' . "\n";
                foreach ($book_structure['back-matter'] as $k => $v) {
                    $title = str_replace('"', '&quot;', $v['post_title']);
                    $html .= '              <li><input id="item-' . $id . '-' . $v['ID'] . '" type="' . $type . '" name="section' . $suffix . '" value="' . $id . '.' . $v['ID'] . '-' . $title . '"> <label for="item-' . $id . '-' . $v['ID'] . '">' . $v['post_title'] . '</label></li>' . "\n";
                }
            }
            $html .= '        </ul>' . "\n" .
                '      </li>' . "\n" .
                '    </ul>' . "\n";
            $class = '';
        }
    }

    $html .= '    <p>' . "\n" .
        '      <input type="submit" class="button button-primary" value="Submit" />' . "\n" .
        '    </p>' . "\n" .
        '  </form>' . "\n" .
        '</body>' . "\n" .
        '</html>' . "\n";
    echo(wp_kses($html, $allowed));
}
